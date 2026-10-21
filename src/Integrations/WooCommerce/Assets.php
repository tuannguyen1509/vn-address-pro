<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class Assets {
    public function register(): void { add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 ); }
    public function enqueue(): void {
        if ( ! Compatibility::request() || ! is_checkout() || is_wc_endpoint_url() ) { return; }
        \Tunlamweb\VNAddressPro\Frontend\Assets::enqueue();
        $codes = array();
        foreach ( WC()->countries->get_states( 'VN' ) ?: array() as $key => $name ) {
            $code = States::from_wc_state_code( (string) $key ); if ( $code !== null ) { $codes[ $key ] = $code; }
        }
        wp_enqueue_script( 'vnap-wc-classic', VNAP_URL . 'assets/js/woocommerce-classic.js', array( 'vnap-address','jquery','wc-checkout','wc-country-select' ), VNAP_VERSION, true );
        wp_localize_script( 'vnap-wc-classic', 'VNAddressProWooConfig', array( 'stateCodes' => $codes, 'wardLabel' => __( 'Phường / Xã / Đặc khu', 'vn-address-pro' ), 'placeholder' => __( 'Chọn Phường / Xã / Đặc khu', 'vn-address-pro' ) ) );
    }
}
