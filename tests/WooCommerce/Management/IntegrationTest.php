<?php
/** Disposable WordPress/WooCommerce only; run with wp eval-file. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\CustomerAddress;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\OrderAddress;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\MyAccount\MyAccountIntegration;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Admin\OrderAddress as AdminAddress;

$count = 0; $check = static function ( $ok, $label ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$count; WP_CLI::line( 'PASS ' . $label ); };
$old_post = $_POST; $old_request = $_REQUEST; $old_user = get_current_user_id(); $order = null; $customer_id = 0;
$old_admin_errors = WC_Admin_Meta_Boxes::$meta_box_errors;
$http = 0; $deny = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline', 'offline' ); };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$deny_mail = static fn() => true; add_filter( 'pre_wp_mail', $deny_mail );
set_error_handler( static function ( $severity, $message, $file, $line ) { if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false; } );
try {
    wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
    $service = Plugin::instance()->address_service(); $a = $service->get_wards( 79 )['wards'][0]; $b = $service->get_wards( 1 )['wards'][0];
    $resolve = static fn( $p, $w, $prefix = 'billing' ) => ( new AddressValidator() )->resolve( array( $prefix . '_country' => 'VN', $prefix . '_state' => 'VN-' . $p, AddressValidator::field( $prefix ) => (string) $w ), $prefix );
    $initial = array( 'billing' => $resolve( 79, $a['code'] ), 'shipping' => $resolve( 1, $b['code'], 'shipping' ) );
    $customer_id = wc_create_new_customer( 'vnap-management-' . wp_generate_uuid4() . '@example.invalid' );
    $customer = new WC_Customer( $customer_id ); $customer->set_billing_country( 'VN' ); $customer->set_shipping_country( 'VN' );
    ( new CustomerAddress() )->write( $customer, $initial ); $customer->save();
    $order = wc_create_order( array( 'customer_id' => $customer_id ) ); $order->set_billing_country( 'VN' ); $order->set_shipping_country( 'VN' ); ( new OrderAddress() )->write( $order, $initial ); $order->save();
    $snapshot = $order->get_data(); $account = new MyAccountIntegration();
    foreach ( array( 'billing','shipping' ) as $prefix ) {
        wc_clear_notices(); $_POST = array( $prefix . '_country' => 'VN', $prefix . '_state' => 'VN-79', AddressValidator::field( $prefix ) => (string) $a['code'], $prefix . '_city' => 'FORGED' );
        $customer = new WC_Customer( $customer_id ); $account->validate( $customer_id, $prefix, array(), $customer ); $customer->save(); $customer = new WC_Customer( $customer_id );
        $check( (int) $customer->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) ) === $a['code'] && $customer->{"get_{$prefix}_city"}() === $a['name'], $prefix . ' account CRUD resolves canonical names and codes' );
        $check( $order->get_data() === $snapshot, 'Account save leaves historical order object unchanged: ' . $prefix );
        foreach ( array( '', '999999999', array( 'bad' ), (string) $b['code'] ) as $bad ) {
            wc_clear_notices(); $_POST[ AddressValidator::field( $prefix ) ] = $bad; $customer = new WC_Customer( $customer_id ); $before = $customer->get_data();
            $account->validate( $customer_id, $prefix, array(), $customer );
            $check( wc_notice_count( 'error' ) > 0 && $customer->get_data() === $before, $prefix . ' rejects absent/unknown/array/mismatched ward without mutation' );
        }
        wc_clear_notices(); $_POST = array( $prefix . '_country' => 'US', $prefix . '_state' => 'CA', $prefix . '_city' => 'Los Angeles' );
        $customer = new WC_Customer( $customer_id ); $customer->{"set_{$prefix}_country"}( 'US' ); $customer->{"set_{$prefix}_city"}( 'Los Angeles' ); $account->validate( $customer_id, $prefix, array(), $customer ); $customer->save();
        $check( ! $customer->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) ) && $customer->{"get_{$prefix}_city"}() === 'Los Angeles', $prefix . ' foreign save clears only applicable codes and keeps native city' );
    }
    $check( wc_get_order( $order->get_id() )->get_billing_city() === $a['name'], 'Persisted historical order survives all customer edits' );
    $_POST = array( 'action' => 'edit_address', 'billing_country' => array( 'VN' ), 'billing_state' => array( 'VN-79' ), 'billing_city' => array( 'bad' ) ); $_REQUEST = array( 'woocommerce-edit-address-nonce' => wp_create_nonce( 'woocommerce-edit_address' ) ); wc_clear_notices(); $account->preflight();
    $check( wc_notice_count( 'error' ) > 0 && $_POST['billing_country'] === '' && $_POST['billing_state'] === '', 'Hostile native arrays rejected before Woo string setters' );
    $admin = new AdminAddress();
    $registered = $GLOBALS['wp_filter']['woocommerce_process_shop_order_meta']->callbacks[39] ?? array();
    $check( (bool) array_filter( $registered, static fn( $callback ) => is_array( $callback['function'] ) && $callback['function'][0] instanceof AdminAddress && $callback['function'][1] === 'prepare' ), 'Admin validation is armed after totals and before native Order Data save' );
    $fields = $admin->fields( array(), $order, 'billing' );
    $check( isset( $fields['vnap_ward'], $fields['vnap_ward_code'] ) && count( $fields['vnap_ward']['options'] ) === 2, 'Native admin fields contain only placeholder and current ward' );
    $check( $admin->fields( array(), $order, 'billing', 'view' ) === array(), 'Technical fields do not duplicate formatted admin address' );
    foreach ( array( 'billing','shipping' ) as $prefix ) {
        $_POST = array( 'vnap_order_address_nonce' => wp_create_nonce( 'vnap_order_address_' . $order->get_id() ), '_' . $prefix . '_country' => 'VN', '_' . $prefix . '_state' => 'VN-1', '_' . $prefix . '_city' => 'FORGED', '_' . $prefix . '_vnap_ward_code' => (string) $b['code'] );
        $customer_before = wp_json_encode( ( new WC_Customer( $customer_id ) )->get_data() );
        $admin->prepare( $order->get_id() ); $loaded = wc_get_order( $order->get_id() ); $loaded->{"set_{$prefix}_state"}( 'VN-1' ); $loaded->{"set_{$prefix}_city"}( 'FORGED' ); $admin->write( $loaded ); $loaded->save(); $order = wc_get_order( $order->get_id() );
        $check( $order->{"get_{$prefix}_city"}() === $b['name'] && (int) $order->get_meta( AddressValidator::meta( $prefix, 'province_code' ) ) === 1, $prefix . ' admin edit writes canonical snapshot through CRUD' );
        $check( ! isset( $_POST[ '_' . $prefix . '_vnap_ward_code' ] ), 'Transport field consumed before older Woo metadata save loop: ' . $prefix );
        $check( wp_json_encode( ( new WC_Customer( $customer_id ) )->get_data() ) === $customer_before, 'Order editing never changes customer: ' . $prefix );
        foreach ( array( (string) $a['code'], array( 'bad' ) ) as $bad ) {
            $_POST = array( 'vnap_order_address_nonce' => wp_create_nonce( 'vnap_order_address_' . $order->get_id() ), '_' . $prefix . '_country' => 'VN', '_' . $prefix . '_state' => 'VN-1', '_' . $prefix . '_city' => 'FORGED', '_' . $prefix . '_vnap_ward_code' => $bad );
            $admin->prepare( $order->get_id() );
            $check( wp_unslash( $_POST[ '_' . $prefix . '_city' ] ) === $b['name'], 'Admin invalid code restores native city: ' . $prefix );
        }
        $_POST = array( '_' . $prefix . '_country' => 'VN', '_' . $prefix . '_state' => 'VN-79', '_' . $prefix . '_city' => 'FORGED', '_' . $prefix . '_vnap_ward_code' => (string) $a['code'] ); $admin->prepare( $order->get_id() );
        $check( $_POST[ '_' . $prefix . '_state' ] === 'VN-1', 'Missing custom nonce cannot change address: ' . $prefix );
    }
    $order->set_billing_city( 'Historical obsolete ward' ); $order->update_meta_data( '_vnap_billing_ward_code', '999999999' ); $order->save();
    $_POST = array( '_billing_country' => 'VN', '_billing_state' => $order->get_billing_state(), '_billing_city' => 'Historical obsolete ward', '_billing_vnap_ward_code' => '999999999' );
    $before = $order->get_data(); $admin->fields( array(), $order, 'billing' ); $admin->prepare( $order->get_id() ); $admin->write( $order );
    $check( $order->get_data() === $before, 'Opening/rendering/no-change save retains obsolete historical snapshot' );
    $_POST = array( 'vnap_order_address_nonce' => wp_create_nonce( 'vnap_order_address_' . $order->get_id() ), '_billing_country' => 'US', '_billing_state' => 'CA', '_billing_city' => 'Los Angeles', '_billing_vnap_ward_code' => '' );
    $admin->prepare( $order->get_id() ); $order->set_billing_country( 'US' ); $order->set_billing_city( 'Los Angeles' ); $admin->write( $order ); $order->save();
    $check( ! $order->get_meta( '_vnap_billing_ward_code' ) && (int) $order->get_meta( '_vnap_shipping_ward_code' ) === $b['code'], 'Admin foreign switch clears only edited address metadata' );
    $check( $order->get_billing_city() === 'Los Angeles', 'Foreign admin city remains native' );
    $customer = new WC_Customer( $customer_id ); $customer->set_billing_country( 'VN' ); ( new CustomerAddress() )->write( $customer, array( 'billing' => $initial['billing'] ) ); $customer->save();
    $details = $admin->customer_details( array(), $customer, $customer_id );
    $check( $details['billing']['vnap_ward_code'] === (string) $a['code'], 'Native customer-load response includes validated canonical code' );
    $customer->delete_meta_data( '_vnap_billing_ward_code' );
    $check( $admin->customer_details( array(), $customer, $customer_id )['billing']['vnap_ward_code'] === '', 'Legacy customer load never guesses a ward from text' );
    $customer->save(); $_POST = array(); wp_set_current_user( $customer_id );
    $before = wp_json_encode( ( new WC_Customer( $customer_id ) )->get_data() );
    $account_fields = $account->fields( array(), 'billing' );
    $check( count( $account_fields['vnap_account_billing_ward']['options'] ) === 1 && $account_fields['vnap_billing_ward_code']['value'] === '', 'Legacy account render retains province but never guesses ward' );
    $check( wp_json_encode( ( new WC_Customer( $customer_id ) )->get_data() ) === $before, 'Account rendering performs no customer writes' );
    $customer->update_meta_data( '_vnap_billing_ward_code', '999999999' ); $customer->save();
    $account_fields = $account->fields( array(), 'billing' );
    $check( count( $account_fields['vnap_account_billing_ward']['options'] ) === 1, 'Obsolete account ward remains unselected against current dataset' );
    wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
    $_POST = array( 'order_status' => 'wc-pending', '_payment_method' => 'bacs', 'customer_user' => 0, 'vnap_order_address_nonce' => wp_create_nonce( 'vnap_order_address_' . $order->get_id() ), '_billing_country' => 'VN', '_billing_state' => 'VN-79', '_billing_city' => 'FORGED', '_billing_vnap_ward_code' => (string) $a['code'], 'vnap_admin_billing_ward' => (string) $a['code'] );
    add_action( 'woocommerce_before_order_object_save', array( $admin, 'write' ), 20 );
    try {
        $admin->prepare( $order->get_id() );
        require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-order-data.php';
        WC_Meta_Box_Order_Data::save( $order->get_id() );
    } finally { remove_action( 'woocommerce_before_order_object_save', array( $admin, 'write' ), 20 ); }
    $order = wc_get_order( $order->get_id() );
    $check( $order->get_billing_state() === 'VN-79' && $order->get_billing_city() === $a['name'], 'Native Order Data save loop retains resolved state/city' );
    $check( (int) $order->get_meta( '_vnap_billing_ward_code' ) === $a['code'] && (int) $order->get_meta( '_vnap_billing_province_code' ) === 79, 'Native save hook persists Phase 7 canonical metadata' );
    $check( $order->get_meta( '_billing_vnap_ward_code' ) === '' && $order->get_meta( 'vnap_admin_billing_ward' ) === '', 'Native old/new Woo save loops persist no parallel transport metadata' );
    $check( $http === 0, 'All account/admin operations use zero outbound HTTP' );
    WP_CLI::success( $count . ' account/admin integration checks passed; WC ' . WC_VERSION . '; HPOS=' . get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) );
} finally {
    if ( $order ) { $order->delete( true ); }
    if ( $customer_id ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $customer_id ); }
    WC_Admin_Meta_Boxes::$meta_box_errors = $old_admin_errors;
    $_POST = $old_post; $_REQUEST = $old_request; wp_set_current_user( $old_user ); wc_clear_notices(); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', $deny_mail ); restore_error_handler();
}
