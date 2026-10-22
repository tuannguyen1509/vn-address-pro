<?php
/** Disposable WordPress + WooCommerce database only: wp eval-file. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
use Tunlamweb\VNAddressPro\Migration\{MigrationService,WooAddressSource,MigrationAudit,PlanCodec};
use Tunlamweb\VNAddressPro\Repository\LocalAddressRepository;
use Tunlamweb\VNAddressPro\Admin\MigrationPage;
use Tunlamweb\VNAddressPro\Sync\DatasetSyncLock;
global $wpdb;

$count = 0; $check = static function ( $ok, $label ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$count; WP_CLI::line( 'PASS ' . $label ); };
$users = array(); $orders = array(); $runs = array(); $actor = get_current_user_id(); $old_index = get_option( MigrationAudit::INDEX, null );
$version = get_option( 'vnap_dataset_version' ); $http = 0; $observed = array(); $observe = static function ( $sql ) use ( &$observed ) { $observed[] = $sql; return $sql; };
$deny = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); }; add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$mail = static fn() => true; add_filter( 'pre_wp_mail', $mail );
set_error_handler( static function ( $severity, $message, $file, $line ) { if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false; } );
try {
    wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
    // Isolate retention from any other fixture's audit entries; restore the index below.
    delete_option( MigrationAudit::INDEX );
    $repo = new LocalAddressRepository(); $ward = $repo->get_wards( 79 )['wards'][0];
    $source = new WooAddressSource(); $service = new MigrationService();
    $make = static function ( $type = 'customer', $state = 'VN-79' ) use ( &$users, &$orders, $ward ) {
        if ( $type === 'customer' ) { $id = wc_create_new_customer( 'vnap-migration-' . wp_generate_uuid4() . '@example.invalid' ); $users[] = $id; $entity = new WC_Customer( $id ); }
        else {
            $entity = wc_create_order(); $orders[] = $entity->get_id();
            $product = wc_get_product( get_option( 'vnap_test_product_id' ) );
            if ( $product ) { $entity->add_product( $product, 1 ); $entity->calculate_totals(); }
            $entity->set_payment_method( 'bacs' );
        }
        foreach ( array( 'billing','shipping' ) as $address ) {
            $entity->{"set_{$address}_country"}( 'VN' ); $entity->{"set_{$address}_state"}( $state ); $entity->{"set_{$address}_city"}( $ward['name'] );
            $entity->{"set_{$address}_first_name"}( 'Private synthetic name' ); $entity->{"set_{$address}_address_1"}( 'Private synthetic street' );
        }
        $entity->save(); return $entity->get_id();
    };
    $scan = static function ( $id, $type = 'customer', $extra = array() ) use ( $service, &$runs ) {
        $r = $service->scan( $extra + array( 'mode' => $type === 'customer' ? 'customers' : 'orders', 'addresses' => 'billing', 'include' => array( $id ) ) );
        if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_code() ); } $runs[] = $r['plan']['id']; return $r;
    };
    $customer = $make(); $order = $make( 'order' ); $other = $make();
    foreach ( array( 'customers','orders' ) as $mode ) {
        $empty_scan = $service->scan( array( 'mode' => $mode, 'include' => array( PHP_INT_MAX ) ) );
        $check( ! is_wp_error( $empty_scan ) && $empty_scan['plan']['items'] === array(), 'Empty source scope returns no candidates: ' . $mode );
    }
    $before_customer = $source->snapshot( $source->load( 'customer', $customer ), 'customer', 'billing' );
    $before_order = $source->snapshot( $source->load( 'order', $order ), 'order', 'billing' );
    add_filter( 'query', $observe );
    $db_before = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} ORDER BY option_name", ARRAY_A );
    $preview = $scan( $customer );
    $db_after = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} ORDER BY option_name", ARRAY_A );
    remove_filter( 'query', $observe ); $writes = array_filter( $observed, static fn( $q ) => preg_match( '/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $q ) );
    $check( ! $writes && $db_before === $db_after, 'Dry-run executes zero database writes and preserves all option rows' );
    $check( $source->snapshot( $source->load( 'customer', $customer ), 'customer', 'billing' ) === $before_customer && $source->snapshot( $source->load( 'order', $order ), 'order', 'billing' ) === $before_order, 'Dry-run customer/order database values unchanged' );
    $check( $preview['plan']['items'][0]['status'] === 'MIGRATABLE_HIGH', 'High confidence preview proposes code enrichment' );
    $check( ! str_contains( wp_json_encode( $preview ), 'Private synthetic' ), 'Preview contains no name/street PII' );
    $check( is_wp_error( $service->apply( $preview['token'], array( 0 ) ) ), 'Explicit confirmation mandatory' );
    $result = $service->apply( $preview['token'], array( 0 ), true );
    $check( ! is_wp_error( $result ) && $result['applied'] === 1, 'Explicit selected customer apply succeeds' );
    $check( $source->snapshot( $source->load( 'customer', $customer ), 'customer', 'billing' ) === $preview['plan']['items'][0]['target'], 'Customer verified against exact preview target' );
    $check( $source->snapshot( $source->load( 'order', $order ), 'order', 'billing' ) === $before_order, 'Customer apply never mutates historical order' );
    $check( ! $source->snapshot( $source->load( 'customer', $customer ), 'customer', 'shipping' )['ward_meta']['exists'], 'Unselected shipping remains unchanged' );
    $check( $service->apply( $preview['token'], array( 0 ), true )['replayed'] === true, 'One-use journal prevents duplicate address apply' );
    $audit = ( new MigrationAudit() )->read( $preview['plan']['id'] );
    $check( $audit['items'][0]['before'] === $before_customer && $audit['items'][0]['after'] === $preview['plan']['items'][0]['target'] && ! str_contains( wp_json_encode( $audit ), 'Private synthetic' ), 'Rollback journal retains exact minimal before/after without unrelated PII' );
    $autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name=%s", MigrationAudit::key( $preview['plan']['id'] ) ) );
    $check( in_array( $autoload, array( 'no','off','auto-off' ), true ), 'Audit never autoloads' );
    $canonical = $scan( $customer ); $check( $canonical['plan']['items'][0]['status'] === 'ALREADY_CANONICAL', 'Applied customer becomes canonical no-op' );
    $stale = $scan( $other ); $c = new WC_Customer( $other ); $c->set_billing_city( 'Changed since preview' ); $c->save();
    $check( $service->apply( $stale['token'], array( 0 ), true )['items'][0]['status'] === 'SKIPPED_STALE', 'Fingerprint blocks stale source overwrite' );
    $changed = $scan( $customer ); update_option( 'vnap_dataset_version', '20990101000000' );
    $check( is_wp_error( $service->apply( $changed['token'], array( 0 ), true ) ), 'Dataset identity change invalidates preview' ); update_option( 'vnap_dataset_version', $version );
    $op = $scan( $order, 'order' ); $unrelated = wc_get_order( $order )->get_data();
    $check( is_wp_error( $service->apply( $op['token'], array( 0 ), true ) ), 'Historical orders require additional confirmation' );
    $or = $service->apply( $op['token'], array( 0 ), true, true ); $check( ! is_wp_error( $or ) && $or['applied'] === 1, 'Confirmed order code enrichment succeeds' );
    $after = wc_get_order( $order )->get_data();
    foreach ( array( 'billing','shipping','status','total','payment_method','customer_id','line_items','shipping_lines','fee_lines','coupon_lines','tax_lines' ) as $key ) { $check( $after[$key] == $unrelated[$key], 'Order preserves unrelated native snapshot/property: ' . $key ); }
    $check( $source->snapshot( $source->load( 'customer', $customer ), 'customer', 'billing' ) === $canonical['plan']['items'][0]['original'], 'Order migration leaves customer unchanged' );
    $medium = $make( 'customer', 'HCM' ); $mp = $scan( $medium );
    $check( $mp['plan']['items'][0]['overall_confidence'] === 'MEDIUM' && $service->apply( $mp['token'], array( 0 ), true )['items'][0]['status'] === 'SKIPPED_CONFIDENCE', 'MEDIUM excluded by default' );
    $mp = $scan( $medium ); $check( $service->apply( $mp['token'], array( 0 ), true, false, true )['applied'] === 1, 'Reviewed MEDIUM requires explicit opt-in' );
    $display_id = $make( 'order', 'HCM' ); $dp = $scan( $display_id, 'order', array( 'order_display' => true ) );
    $check( $dp['plan']['items'][0]['target']['state'] === 'VN-79' && $service->apply( $dp['token'], array( 0 ), true, true, true )['applied'] === 1, 'Separate order display preview/apply canonicalizes only when requested' );
    $check( is_wp_error( ( new PlanCodec() )->decode( $preview['token'] . 'x' ) ), 'Tampered signed plan rejected' );
    $expired = $preview['plan']; $expired['expires'] = time() - 1;
    $check( is_wp_error( ( new PlanCodec() )->decode( ( new PlanCodec() )->encode( $expired ) ) ), 'Expired plan rejected' );
    $foreign = $preview['plan']; $foreign['user'] = PHP_INT_MAX;
    $check( is_wp_error( ( new PlanCodec() )->decode( ( new PlanCodec() )->encode( $foreign ) ) ), 'Plan bound to creating user' );
    $foreign['user'] = get_current_user_id(); $foreign['site'] = PHP_INT_MAX;
    $check( is_wp_error( ( new PlanCodec() )->decode( ( new PlanCodec() )->encode( $foreign ) ) ), 'Plan bound to site' );
    $page = new MigrationPage(); $check( $page->authorize( 'scan', wp_create_nonce( 'vnap_migration_scan' ) ) && ! $page->authorize( 'scan', 'invalid' ), 'Admin action validates nonce' );
    $sub = wp_create_user( 'vnap-sub-' . wp_generate_uuid4(), wp_generate_password(), 'vnap-sub-' . wp_generate_uuid4() . '@example.invalid' ); $users[] = $sub; wp_set_current_user( $sub );
    $check( ! MigrationService::allowed() && is_wp_error( $service->scan() ) && ! $page->authorize( 'scan', wp_create_nonce( 'vnap_migration_scan' ) ), 'Subscriber cannot scan/apply even with own nonce' );
    wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
    foreach ( array( array( 'batch' => 51 ), array( 'page' => array() ), array( 'from' => '2026-02-30' ), array( 'from' => '2026-10-22', 'to' => '2026-01-01' ), array( 'mode' => 'invalid' ) ) as $bad ) { $check( is_wp_error( $service->scope( $bad ) ), 'Invalid bounded scope rejected' ); }
    $scope = $service->scope( array( 'include' => $users, 'batch' => 1 ) ); $first = $source->ids( 'customer', $scope, 1 ); $scope['page'] = 2; $second = $source->ids( 'customer', $scope, 1 );
    $check( count( $first ) === 2 && $second[0] === $first[1], 'Customer scan uses bounded overlapping lookahead pagination' );
    $scope = $service->scope( array( 'include' => $orders, 'from' => wp_date( 'Y-m-d' ), 'to' => wp_date( 'Y-m-d' ) ) );
    $check( in_array( $order, $source->ids( 'order', $scope, 25 ), true ), 'Woo order date range includes current day across storage modes' );
    $scope['from'] = '2000-01-01'; $scope['to'] = '2000-01-02'; $check( ! $source->ids( 'order', $scope, 25 ), 'Order date range excludes out-of-range records' );
    $scope['include'] = array(); $check( ! $source->ids( 'order', $scope, 25 ), 'Native Woo date query also excludes out-of-range records without an ID allowlist' );
    $scope['from'] = wp_date( 'Y-m-d' ); $scope['to'] = wp_date( 'Y-m-d' );
    $queried = $source->ids( 'order', $scope, 1 ); $check( count( $queried ) <= 2 && $queried, 'Native Woo scan query bounded with date range and lookahead' );
    $check( $service->scope( array( 'days' => 1 ) )['from'] === wp_date( 'Y-m-d' ), 'Last one calendar day includes today' );
    $lock = new DatasetSyncLock(); $lease = $lock->acquire(); $check( is_wp_error( $service->scan() ), 'Dataset sync lease blocks migration scan' ); $lock->release( $lease );
    $broken_version = get_option( 'vnap_dataset_metadata' ); update_option( 'vnap_dataset_metadata', array() );
    $check( is_wp_error( $service->scan() ), 'Unhealthy dataset blocks migration without remote fallback' ); update_option( 'vnap_dataset_metadata', $broken_version );
    $fail_id = $make(); $ok_id = $make(); $partial = $service->scan( array( 'include' => array( $fail_id, $ok_id ), 'addresses' => 'billing' ) ); $runs[] = $partial['plan']['id'];
    $fail = static function ( $entity ) use ( $fail_id ) { if ( $entity->get_id() === $fail_id ) { throw new RuntimeException( 'Synthetic save failure' ); } }; add_action( 'woocommerce_before_customer_object_save', $fail );
    $r = $service->apply( $partial['token'], array( 0,1 ), true ); remove_action( 'woocommerce_before_customer_object_save', $fail );
    $check( $r['failed'] === 1 && $r['applied'] === 1, 'Per-record save failure does not stop independent records' );
    $invalid_id = $make(); $invalid = new WC_Customer( $invalid_id ); $invalid->update_meta_data( '_vnap_billing_ward_code', array( 'private' => 'Do not leak' ) ); $invalid->save();
    $invalid_plan = $scan( $invalid_id );
    $check( $invalid_plan['plan']['items'][0]['status'] === 'INVALID_DATA' && ! str_contains( wp_json_encode( $invalid_plan ), 'Do not leak' ), 'Malformed metadata redacted and never eligible' );
    $check( $service->apply( $invalid_plan['token'], array( 0 ), true )['applied'] === 0, 'Forged selection cannot apply invalid/LOW data' );
    $scope = $service->scope( array( 'include' => $orders, 'batch' => 1 ) ); $first = $source->ids( 'order', $scope, 1 ); $scope['page'] = 2; $second = $source->ids( 'order', $scope, 1 );
    $check( count( $first ) === 2 && $second[0] === $first[1], 'Order pagination uses bounded Woo CRUD query' );
    $check( is_wp_error( $service->scope( array( 'mode' => 'both', 'batch' => 1 ) ) ), 'Combined source cannot exceed requested entity batch' );
    $journal_id = $make(); $journal_plan = $scan( $journal_id );
    $reject_index = static fn( $new, $old ) => $old; add_filter( 'pre_update_option_' . MigrationAudit::INDEX, $reject_index, 10, 2 );
    $blocked = $service->apply( $journal_plan['token'], array( 0 ), true ); remove_filter( 'pre_update_option_' . MigrationAudit::INDEX, $reject_index );
    $check( is_wp_error( $blocked ) && ! ( new MigrationAudit() )->read( $journal_plan['plan']['id'] ), 'Audit index failure blocks apply and removes unindexed before-journal' );
    $check( $source->snapshot( $source->load( 'customer', $journal_id ), 'customer', 'billing' ) === $journal_plan['plan']['items'][0]['original'], 'Audit persistence failure performs zero customer address writes' );
    $audit = new MigrationAudit();
    for ( $i = 0; $i < MigrationAudit::RETAIN + 1; ++$i ) { $id = wp_generate_uuid4(); $runs[] = $id; $check( $audit->begin( array( 'id' => $id ) ), 'Bounded audit journal creation' ); }
    $check( count( $audit->recent() ) === MigrationAudit::RETAIN && ! $audit->read( $runs[ count( $runs ) - MigrationAudit::RETAIN - 1 ] ), 'Only latest 20 journals retained; older owned journal deleted' );
    $check( $http === 0, 'Entire migration remains offline: zero HTTP attempts' );
    WP_CLI::success( $count . ' migration integration checks passed; storage=' . get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) );
} finally {
    restore_error_handler(); remove_filter( 'query', $observe ); update_option( 'vnap_dataset_version', $version );
    if ( isset( $broken_version ) ) { update_option( 'vnap_dataset_metadata', $broken_version ); }
    if ( isset( $fail ) ) { remove_action( 'woocommerce_before_customer_object_save', $fail ); }
    if ( isset( $reject_index ) ) { remove_filter( 'pre_update_option_' . MigrationAudit::INDEX, $reject_index ); }
    foreach ( $orders as $id ) { $o = wc_get_order( $id ); if ( $o ) { $o->delete( true ); } }
    require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $id ) { wp_delete_user( $id ); }
    foreach ( $runs as $id ) { delete_option( MigrationAudit::key( $id ) ); }
    if ( $old_index === null ) { delete_option( MigrationAudit::INDEX ); } else { update_option( MigrationAudit::INDEX, $old_index, false ); }
    wp_set_current_user( $actor ); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', $mail );
}
