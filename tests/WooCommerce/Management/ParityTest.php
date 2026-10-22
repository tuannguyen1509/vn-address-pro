<?php
/** Disposable Woo site, after its Checkout Setup. No real order or customer is saved. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\ClassicCheckout;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Blocks\StoreApi;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\MyAccount\MyAccountIntegration;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
$page_id = wc_get_page_id( 'checkout' ); $content = get_post( $page_id )->post_content; $post = $_POST;
WC()->initialize_session(); WC()->initialize_cart(); $customer = WC()->customer; $cart = WC()->cart;
$http = 0; $deny = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); }; add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
try {
    wp_update_post( array( 'ID' => $page_id, 'post_content' => '[woocommerce_checkout]' ) );
    WC()->customer = new WC_Customer(); WC()->cart = new WC_Cart(); WC()->cart->add_to_cart( (int) get_option( 'vnap_test_product_id' ) );
    $data = array( 'ship_to_different_address' => 1 ); $request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' ); $extensions = array();
    foreach ( array( 'billing' => 79, 'shipping' => 1 ) as $prefix => $province ) {
        $ward = \Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_wards( $province )['wards'][0];
        $data += array( $prefix . '_country' => 'VN', $prefix . '_state' => 'VN-' . $province, $prefix . '_city' => 'FORGED', AddressValidator::field( $prefix ) => (string) $ward['code'] );
        $request->set_param( $prefix . '_address', array( 'country' => 'VN', 'state' => 'VN-' . $province, 'city' => 'FORGED' ) );
        $extensions[ $prefix . 'WardCode' ] = (string) $ward['code'];
    }
    $request->set_param( 'extensions', array( 'vn-address-pro' => $extensions ) );
    $classic = new WC_Customer(); $block = new WC_Customer(); $account = new WC_Customer();
    $checkout = new ClassicCheckout(); $normalized = $checkout->normalize( $data ); $checkout->customer( $classic, $normalized ); ( new StoreApi() )->customer( $block, $request );
    $_POST = $data; wc_clear_notices(); $integration = new MyAccountIntegration();
    foreach ( array( 'billing','shipping' ) as $prefix ) { $integration->validate( 0, $prefix, array(), $account ); }
    foreach ( array( 'billing','shipping' ) as $prefix ) {
        foreach ( array( 'state','city' ) as $part ) {
            $method = "get_{$prefix}_{$part}";
            if ( $classic->$method() !== $block->$method() || $classic->$method() !== $account->$method() ) { throw new RuntimeException( 'Native cross-surface address mismatch' ); }
        }
        foreach ( array( 'province_code','ward_code' ) as $part ) {
            $key = AddressValidator::meta( $prefix, $part );
            if ( $classic->get_meta( $key ) !== $block->get_meta( $key ) || $classic->get_meta( $key ) !== $account->get_meta( $key ) ) { throw new RuntimeException( 'Canonical cross-surface metadata mismatch' ); }
        }
        WP_CLI::line( 'PASS Classic/Block/My Account ' . $prefix . ' native fields and canonical metadata parity' );
    }
    if ( $http || wc_notice_count( 'error' ) ) { throw new RuntimeException( 'Parity validation/HTTP failure' ); }
    WP_CLI::success( 'Cross-surface parity passed; independent billing/shipping; zero outbound HTTP.' );
} finally {
    wp_update_post( array( 'ID' => $page_id, 'post_content' => $content ) ); $_POST = $post; WC()->customer = $customer; WC()->cart = $cart; wc_clear_notices(); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX );
}
