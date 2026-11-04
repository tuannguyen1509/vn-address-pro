<?php
/** Run with wp eval-file only in a disposable WordPress/WooCommerce database. */
namespace Tunlamweb\VNAddressPro\Tests;

use Tunlamweb\VNAddressPro\Migration\{ShippingConfiguration,MigrationService,PlanCodec};
use Tunlamweb\VNAddressPro\Admin\ShippingConfigurationPage;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }

global $wpdb;
$checks = 0;
$check = static function ( $ok, string $message ) use ( &$checks ): void {
    if ( ! $ok ) { throw new \RuntimeException( $message ); }
    ++$checks; echo 'PASS ' . $message . "\n";
};
$original_user = get_current_user_id();
$original_store = get_option( 'woocommerce_default_country' );
$original_history = get_option( ShippingConfiguration::HISTORY, null );
$zone = new \WC_Shipping_Zone();
$zone->set_zone_name( 'Reusable configuration fixture <script>' );
$zone->set_zone_order( 17 );
$zone->add_location( 'VN:HOCHIMINH', 'state' );
$zone->add_location( 'VN:VINHLONG', 'state' );
$zone->add_location( 'VN:UNKNOWNPROVINCE', 'state' );
$zone->add_location( 'US:CA', 'state' );
$zone->add_location( 'VN:VN-1', 'state' );
$zone->add_location( '70000', 'postcode' );
$zone->save();
$method = $zone->add_shipping_method( 'flat_rate' );
$method_key = 'woocommerce_flat_rate_' . $method . '_settings';
update_option( $method_key, array( 'cost' => '12345', 'title' => 'Fixture delivery' ) );
$snapshot = static function () use ( $wpdb, $zone ): array {
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE zone_id=%d ORDER BY location_id", $zone->get_id() ), ARRAY_A );
};
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $admin );
$deny = static fn() => new \WP_Error( 'offline' );
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$old_errors = $wpdb->suppress_errors();
try {
    update_option( 'woocommerce_default_country', 'VN:HOCHIMINH' );
    $service = new ShippingConfiguration();
    $rows = array( array( 'code' => 79, 'name' => 'Thành phố Hồ Chí Minh' ) );
    $check( $service->target( 'VN:HOCHIMINH', $rows ) === 'VN:VN-79', 'compact name resolves against supplied dataset' );
    $check( $service->target( 'VN:HOCHIMINH', array_merge( $rows, $rows ) ) === null, 'ambiguous compact names are unresolved' );
    foreach ( array( 'VN:79','VN:VN-79','US:HOCHIMINH','VN:UNKNOWN','VN:hoChiMinh' ) as $raw ) { $check( $service->target( $raw, $rows ) === null, 'conservative mapping: ' . $raw ); }
    $before = $snapshot(); $options_before = $wpdb->get_results( "SELECT * FROM {$wpdb->options} ORDER BY option_id", ARRAY_A );
    $scan = $service->scan();
    $check( ! is_wp_error( $scan ), 'scan succeeds with healthy local data offline' );
    $check( $snapshot() === $before && $wpdb->get_results( "SELECT * FROM {$wpdb->options} ORDER BY option_id", ARRAY_A ) === $options_before, 'scan does not persist rows or options' );
    $selected = array(); $unknown = null;
    foreach ( $scan['plan']['items'] as $i => $item ) {
        if ( ( $item['source']['zone_id'] ?? null ) == $zone->get_id() ) {
            if ( $item['after'] !== null ) { $selected[] = $i; } else { $unknown = $i; }
        }
        if ( $item['kind'] === 'store' ) { $selected[] = $i; }
    }
    $check( count( $selected ) === 3 && $unknown !== null, 'two zone mappings plus store; unknown remains visible' );
    $check( is_wp_error( $service->apply( $scan['token'], $selected, false ) ), 'explicit confirmation required' );
    $check( is_wp_error( $service->apply( $scan['token'], array( $unknown ), true ) ), 'unresolved mapping cannot apply' );
    $check( is_wp_error( $service->apply( $scan['token'], array( array( 0 ) ), true ) ), 'nested selection rejected' );
    $check( is_wp_error( $service->apply( $scan['token'] . 'x', $selected, true ) ), 'tampered signature rejected' );
    $check( is_wp_error( ( new MigrationService() )->apply( $scan['token'], $selected, true ) ), 'address migration rejects configuration preview' );
    $address_plan = $scan['plan']; unset( $address_plan['purpose'] );
    $check( is_wp_error( $service->apply( ( new PlanCodec() )->encode( $address_plan ), $selected, true ) ), 'configuration rejects address preview' );
    wp_set_current_user( 0 );
    $check( is_wp_error( $service->scan() ) && is_wp_error( $service->apply( $scan['token'], $selected, true ) ), 'anonymous access denied' );
    wp_set_current_user( $admin );
    $page = new ShippingConfigurationPage();
    $check( ! $page->authorize( 'scan', 'invalid' ) && ! $page->authorize( 'delete', wp_create_nonce( 'vnap_shipping_delete' ) ) && $page->authorize( 'scan', wp_create_nonce( 'vnap_shipping_scan' ) ), 'UI nonce and command gates' );
    $expired = $scan['plan']; $expired['expires'] = time() - 1;
    $check( is_wp_error( $service->apply( ( new PlanCodec() )->encode( $expired ), $selected, true ) ), 'expired preview rejected' );
    $other_user = $scan['plan']; $other_user['user'] = $admin + 1000;
    $check( is_wp_error( $service->apply( ( new PlanCodec() )->encode( $other_user ), $selected, true ) ), 'preview bound to requesting user' );
    $other_site = $scan['plan']; $other_site['site'] = get_current_blog_id() + 1;
    $check( is_wp_error( $service->apply( ( new PlanCodec() )->encode( $other_site ), $selected, true ) ), 'preview bound to requesting site' );
    $dataset = get_option( 'vnap_dataset_metadata' );
    update_option( 'vnap_dataset_metadata', $dataset + array( 'test_revision' => 1 ) );
    $check( is_wp_error( $service->apply( $scan['token'], $selected, true ) ) && $snapshot() === $before, 'dataset change invalidates preview' );
    update_option( 'vnap_dataset_metadata', $dataset );
    $wpdb->insert( $wpdb->prefix . 'woocommerce_shipping_zone_locations', array( 'zone_id' => $zone->get_id(), 'location_code' => '80000', 'location_type' => 'postcode' ) );
    $extra_id = $wpdb->insert_id;
    $check( is_wp_error( $service->apply( $scan['token'], $selected, true ) ), 'changed geographic coverage invalidates preview' );
    $wpdb->delete( $wpdb->prefix . 'woocommerce_shipping_zone_locations', array( 'location_id' => $extra_id ) );
    // One selected source changes after preview: preceding writes must roll back too.
    update_option( 'woocommerce_default_country', 'VN:VINHLONG' );
    $check( is_wp_error( $service->apply( $scan['token'], $selected, true ) ) && $snapshot() === $before, 'stale source aborts whole batch without partial zone writes' );
    update_option( 'woocommerce_default_country', 'VN:HOCHIMINH' );
    // Inject journal failure after all configuration updates to exercise transactional rollback.
    $fail_journal = static function ( string $sql ): string {
        return str_contains( $sql, "'" . ShippingConfiguration::HISTORY . "'" ) && str_starts_with( $sql, 'INSERT INTO' ) ? 'INSERT INTO vnap_nonexistent_test_table VALUES (1)' : $sql;
    };
    add_filter( 'query', $fail_journal );
    $failed = $service->apply( $scan['token'], $selected, true ); remove_filter( 'query', $fail_journal );
    $check( is_wp_error( $failed ) && $snapshot() === $before && get_option( 'woocommerce_default_country' ) === 'VN:HOCHIMINH', 'journal failure rolls back zone and store updates' );
    $result = $service->apply( $scan['token'], $selected, true );
    $check( ! is_wp_error( $result ) && $result['applied'] === 3, 'selected configuration batch applied' );
    $after = $snapshot();
    $check( in_array( 'VN:VN-79', array_column( $after, 'location_code' ), true ) && in_array( 'VN:VN-86', array_column( $after, 'location_code' ), true ), 'canonical zone identifiers persisted' );
    $check( get_option( 'woocommerce_default_country' ) === 'VN:VN-79', 'store province persisted and option cache refreshed' );
    $unchanged_before = array_values( array_filter( $before, static fn( $r ) => ! in_array( $r['location_code'], array( 'VN:HOCHIMINH','VN:VINHLONG' ), true ) ) );
    $unchanged_after = array_values( array_filter( $after, static fn( $r ) => ! in_array( $r['location_code'], array( 'VN:VN-79','VN:VN-86' ), true ) ) );
    $check( $unchanged_before === $unchanged_after, 'foreign, canonical, postcode and unresolved rows preserved' );
    $fresh_zone = new \WC_Shipping_Zone( $zone->get_id() );
    $check( $fresh_zone->get_zone_order() === 17 && get_option( $method_key ) === array( 'cost' => '12345', 'title' => 'Fixture delivery' ), 'zone order and delivery price preserved' );
    $match = \WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => array( 'country' => 'VN', 'state' => 'VN-79', 'postcode' => '70000', 'city' => '' ) ) );
    $check( $match->get_id() === $zone->get_id(), 'native Woo zone matcher sees converted state' );
    $history = get_option( ShippingConfiguration::HISTORY );
    $check( $history[0]['id'] === $scan['plan']['id'] && $history[0]['items'][0]['before'] === 'VN:HOCHIMINH', 'before/after journal retained' );
    $replay = $service->apply( $scan['token'], $selected, true );
    $check( ! is_wp_error( $replay ) && ( $replay['replayed'] ?? false ) && count( get_option( ShippingConfiguration::HISTORY ) ) === count( $history ), 'preview replay is idempotent' );
    echo 'SUCCESS ' . $checks . " checks\n";
} finally {
    $zone->delete(); delete_option( $method_key );
    update_option( 'woocommerce_default_country', $original_store );
    if ( $original_history === null ) { delete_option( ShippingConfiguration::HISTORY ); } else { update_option( ShippingConfiguration::HISTORY, $original_history, false ); }
    wp_set_current_user( $original_user ); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); $wpdb->suppress_errors( $old_errors );
}
