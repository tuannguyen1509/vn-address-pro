<?php
/** Copy into disposable MU plugins only. Never loaded by the production plugin. */
defined( 'ABSPATH' ) || exit;
add_filter( 'woocommerce_admin_disabled', '__return_true' );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
    if ( str_contains( $url, 'provinces.open-api.vn' ) ) { update_option( 'vnap_test_address_http_count', (int) get_option( 'vnap_test_address_http_count', 0 ) + 1, false ); }
    return new WP_Error( 'vnap_test_offline', 'Outbound requests are denied in the test environment.' );
}, PHP_INT_MAX, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_action( 'template_redirect', static function () {
    if ( ! function_exists( 'wc_get_order' ) || ! isset( $_GET['vnap_test_report'], $_GET['key'] ) || ! is_scalar( $_GET['vnap_test_report'] ) || ! is_string( $_GET['key'] ) ) { return; }
    $order = wc_get_order( absint( $_GET['vnap_test_report'] ) );
    if ( ! $order || ! hash_equals( $order->get_order_key(), wp_unslash( $_GET['key'] ) ) ) { status_header( 403 ); exit; }
    $customer = $order->get_customer_id() ? new WC_Customer( $order->get_customer_id() ) : null;
    wp_send_json( array( 'billing_state' => $order->get_billing_state(), 'billing_city' => $order->get_billing_city(), 'shipping_city' => $order->get_shipping_city(), 'billing_code' => $order->get_meta( '_vnap_billing_ward_code' ), 'shipping_code' => $order->get_meta( '_vnap_shipping_ward_code' ), 'customer_id' => $order->get_customer_id(), 'customer_code' => $customer ? $customer->get_meta( '_vnap_billing_ward_code' ) : '', 'formatted' => $order->get_formatted_billing_address(), 'payment' => $order->get_payment_method() ) );
} );
add_filter( 'woocommerce_available_payment_gateways', static function ( $gateways ) {
    if ( isset( $gateways['cod'] ) ) {
        if ( ! class_exists( 'VnapTestCOD', false ) ) {
            class VnapTestCOD extends WC_Gateway_COD {
                public function process_payment( $order_id ) {
                    if ( ! empty( $_POST['vnap_test_payment_fail'] ) ) { throw new Exception( 'Test payment failure; retry checkout.' ); }
                    return parent::process_payment( $order_id );
                }
            }
        }
        $gateways['cod'] = new VnapTestCOD();
    }
    return $gateways;
} );
add_action( 'init', static function () {
    foreach ( array( '_maybe_update_core','_maybe_update_plugins','_maybe_update_themes' ) as $callback ) { remove_action( 'admin_init', $callback ); }
    remove_action( 'load-plugins.php', 'wp_update_plugins' );
} );
