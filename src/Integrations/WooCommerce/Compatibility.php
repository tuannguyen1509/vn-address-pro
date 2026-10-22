<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class Compatibility {
    private static bool $block_context = false;
    // HPOS became stable/default for new stores in 8.2; CRUD APIs predate this baseline.
    public const MIN_VERSION = '8.2.0';
    public static function supported(): bool { return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MIN_VERSION, '>=' ); }
    public static function supports_blocks(): bool {
        return self::supported() && interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' );
    }
    public static function block_address_context(): bool {
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        return self::$block_context || ( self::checkout_type() === 'block' && is_checkout() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST && is_string( $route ) && str_starts_with( $route, '/wc/store/' ) );
    }
    public static function use_block_context(): void {
        self::$block_context = true;
        // A theme template may render Blocks even when the page content is Classic.
        // Woo's enqueue hook confirms the renderer before its assets/data are enqueued.
        wp_dequeue_script( 'vnap-wc-classic' );
    }
    public static function checkout_type(): string {
        if ( self::$block_context ) { return 'block'; }
        if ( ! function_exists( 'wc_get_page_id' ) ) { return 'absent'; }
        $page = get_post( wc_get_page_id( 'checkout' ) );
        if ( ! $page ) { return 'unknown'; }
        if ( has_block( 'woocommerce/checkout', $page ) ) { return 'block'; }
        return has_shortcode( $page->post_content, 'woocommerce_checkout' ) ? 'classic' : 'unknown';
    }
    public static function request(): bool {
        return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ( ! is_admin() || wp_doing_ajax() ) && self::checkout_type() === 'classic';
    }
    public static function declare(): void {
        if ( self::supported() && class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            // Verified against Woo 8.2.0 and 11.2.1, both CRUD datastores.
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VNAP_FILE, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', VNAP_FILE, self::supports_blocks() );
        }
    }
    public static function status(): array {
        $detected = defined( 'WC_VERSION' );
        return array(
            __( 'WooCommerce Version', 'vn-address-pro' ) => $detected ? WC_VERSION : __( 'Not installed or inactive', 'vn-address-pro' ),
            __( 'Woo Integration', 'vn-address-pro' ) => ! $detected ? __( 'Inactive; core remains independent', 'vn-address-pro' ) : ( self::supported() ? __( 'Enabled', 'vn-address-pro' ) : __( 'Unsupported WooCommerce version', 'vn-address-pro' ) ),
            __( 'Checkout Type', 'vn-address-pro' ) => self::checkout_type(),
            __( 'Woo My Account integration', 'vn-address-pro' ) => self::supported() ? __( 'Active', 'vn-address-pro' ) : __( 'Inactive', 'vn-address-pro' ),
            __( 'Woo Order Admin integration', 'vn-address-pro' ) => self::supported() ? __( 'Active', 'vn-address-pro' ) : __( 'Inactive', 'vn-address-pro' ),
            __( 'Checkout Blocks', 'vn-address-pro' ) => self::supports_blocks() ? __( 'Supported', 'vn-address-pro' ) : __( 'Unavailable; requires supported WooCommerce Blocks APIs', 'vn-address-pro' ),
            __( 'HPOS Enabled', 'vn-address-pro' ) => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? __( 'Yes', 'vn-address-pro' ) : __( 'No', 'vn-address-pro' ),
        );
    }
}
