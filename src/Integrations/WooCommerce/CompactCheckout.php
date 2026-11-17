<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
use Tunlamweb\VNAddressPro\Support\CheckoutPreferences;
defined( 'ABSPATH' ) || exit;

/** Keep native field IDs, validation and templates. Never change saved addresses. */
final class CompactCheckout {
    public function register(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 25 );
        add_filter( 'pre_option_woocommerce_checkout_company_field', array( $this, 'company' ) );
        add_filter( 'woocommerce_get_country_locale', array( $this, 'locale' ), 35 );
        add_filter( 'woocommerce_ship_to_different_address_checked', array( $this, 'different_address' ) );
    }
    public static function context(): bool {
        return Compatibility::block_address_context() || ( ! is_admin() && function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url() );
    }
    public function company( $pre ) {
        return CheckoutPreferences::get( 'compact' ) && self::context() ? 'hidden' : $pre;
    }
    public function different_address( $checked ) { return CheckoutPreferences::get( 'compact' ) ? false : $checked; }
    private function postcode_needed(): bool {
        foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
            foreach ( $zone['zone_locations'] ?? array() as $location ) { if ( $location->type === 'postcode' ) { return true; } }
        }
        return false;
    }
    public function locale( array $locale ): array {
        if ( ! CheckoutPreferences::get( 'compact' ) || ! self::context() ) { return $locale; }
        // Keep international locale requirements and postcode-based shipping intact.
        if ( ! $this->postcode_needed() && Compatibility::block_address_context() ) {
            $locale['VN']['postcode'] = array_merge( $locale['VN']['postcode'] ?? array(), array( 'hidden' => true, 'required' => false ) );
        }
        if ( Compatibility::block_address_context() ) { $locale['VN']['address_2'] = array_merge( $locale['VN']['address_2'] ?? array(), array( 'hidden' => true, 'required' => false ) ); }
        return $locale;
    }
    public function assets(): void {
        if ( ! Compatibility::request() || ! is_checkout() || is_wc_endpoint_url() ) { return; }
        if ( ! CheckoutPreferences::get( 'compact' ) && ! CheckoutPreferences::get( 'vat' ) ) { return; }
        wp_enqueue_script( 'vnap-checkout-experience', VNAP_URL . 'assets/js/checkout-experience.js', array( 'jquery','wc-checkout' ), VNAP_VERSION, true );
        wp_localize_script( 'vnap-checkout-experience', 'VNAPCheckoutExperience', array(
            'compact' => CheckoutPreferences::get( 'compact' ),
            'address' => __( 'Địa chỉ', 'vn-address-pro' ),
            'details' => __( 'Thêm chi tiết địa chỉ (không bắt buộc)', 'vn-address-pro' ),
            'notes' => __( 'Thêm ghi chú đơn hàng (không bắt buộc)', 'vn-address-pro' ),
        ) );
    }
}
