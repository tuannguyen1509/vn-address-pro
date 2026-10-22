<?php
/** Disposable site only. Uses the first administrator as the browser test customer. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\CustomerAddress;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\OrderAddress;
$service = \Tunlamweb\VNAddressPro\Plugin::instance()->address_service();
// Native admin avatars otherwise request Gravatar; isolate address/network checks.
update_option( 'show_avatars', 0 );
$a = $service->get_wards( 79 )['wards']; $b = $service->get_wards( 1 )['wards'];
$user = get_users( array( 'role' => 'administrator','number' => 1 ) )[0];
$customer = new WC_Customer( $user->ID ); $selections = array();
foreach ( array( 'billing','shipping' ) as $prefix ) {
    foreach ( array( 'country' => 'VN','state' => 'VN-79','city' => $a[0]['name'],'first_name' => 'Test','last_name' => 'Customer','address_1' => 'Test street','postcode' => '700000' ) as $part => $value ) { $customer->{"set_{$prefix}_{$part}"}( $value ); }
    $selections[ $prefix ] = ( new AddressValidator() )->resolve( array( $prefix . '_country' => 'VN', $prefix . '_state' => 'VN-79', AddressValidator::field( $prefix ) => (string) $a[0]['code'] ), $prefix );
}
$customer->set_billing_phone( '0901234567' ); $customer->set_billing_email( 'management@example.invalid' ); ( new CustomerAddress() )->write( $customer, $selections ); $customer->save();
$orders = array();
foreach ( array( 'classic','block','legacy','obsolete' ) as $kind ) {
    $order = null;
    if ( in_array( $kind, array( 'classic','block' ), true ) ) {
        $origin = $kind === 'block' ? 'store-api' : 'checkout';
        foreach ( wc_get_orders( array( 'limit' => 100, 'orderby' => 'ID', 'order' => 'DESC' ) ) as $candidate ) {
            if ( $candidate->get_created_via() === $origin && count( $candidate->get_items() ) > 0 && $candidate->get_billing_country() === 'VN' ) { $order = $candidate; break; }
        }
        if ( ! $order ) { throw new RuntimeException( 'Run actual Classic and Block checkout browser/API tests before Management Setup.' ); }
        $order->set_customer_id( $user->ID );
    } else { $order = wc_create_order( array( 'customer_id' => $user->ID ) ); }
    $order->set_address( $customer->get_billing(), 'billing' ); $order->set_address( $customer->get_shipping(), 'shipping' );
    if ( $kind !== 'legacy' ) { ( new OrderAddress() )->write( $order, $selections ); }
    if ( $kind === 'obsolete' ) { $order->set_billing_city( 'Historical obsolete ward' ); $order->update_meta_data( '_vnap_billing_ward_code', '999999999' ); }
    $order->save(); $orders[ $kind ] = array( 'id' => $order->get_id(), 'url' => $order->get_edit_order_url(), 'key' => $order->get_order_key(), 'actualCheckout' => count( $order->get_items() ) > 0 );
}
flush_rewrite_rules();
WP_CLI::line( wp_json_encode( array( 'a' => array_slice( $a, 0, 2 ), 'b' => array_slice( $b, 0, 2 ), 'orders' => $orders, 'account' => wc_get_page_permalink( 'myaccount' ), 'userId' => $user->ID ) ) );
