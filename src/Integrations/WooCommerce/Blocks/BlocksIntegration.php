<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Tunlamweb\VNAddressPro\Frontend\Assets as FrontendAssets;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Compatibility;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\States;

defined( 'ABSPATH' ) || exit;

final class BlocksIntegration implements IntegrationInterface {
    public function get_name() { return 'vn-address-pro'; }
    public function initialize() {
        FrontendAssets::register();
        $asset = require VNAP_PATH . 'assets/blocks/checkout.asset.php';
        wp_register_script( 'vnap-wc-blocks', VNAP_URL . 'assets/blocks/checkout.js', $asset['dependencies'], VNAP_VERSION, true );
        wp_set_script_translations( 'vnap-wc-blocks', 'vn-address-pro', VNAP_PATH . 'languages' );
        $codes = array();
        foreach ( WC()->countries->get_states( 'VN' ) ?: array() as $key => $label ) {
            $code = States::from_wc_state_code( (string) $key );
            if ( $code !== null ) { $codes[ $key ] = (string) $code; }
        }
        wp_localize_script( 'vnap-wc-blocks', 'VNAddressProBlocksConfig', array(
            'blocks' => array_map( static fn( $type ) => json_decode( file_get_contents( VNAP_PATH . 'assets/blocks/' . $type . '/block.json' ), true ), array( 'shipping', 'billing' ) ),
            'editor' => is_admin(),
            'forcedBilling' => wc_ship_to_billing_address_only(),
            'stateCodes' => $codes,
        ) );
    }
    public function get_script_handles() {
        return array( 'vnap-wc-blocks' );
    }
    public function get_editor_script_handles() { return array( 'vnap-wc-blocks' ); }
    public function get_script_data() { return array(); }
    public static function register(): void {
        add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_before', static function () {
            Compatibility::use_block_context();
            wp_enqueue_style( 'vnap-address', VNAP_URL . 'assets/css/vn-address.css', array(), VNAP_VERSION );
        } );
        add_action( 'init', static function () {
            foreach ( array( 'billing','shipping' ) as $type ) {
                register_block_type( VNAP_PATH . 'assets/blocks/' . $type, array(
                    'render_callback' => static fn() => '<div data-block-name="vn-address-pro/' . $type . '-ward"></div>',
                ) );
            }
        } );
        add_action( 'woocommerce_blocks_checkout_block_registration', static function ( $registry ) { $registry->register( new self() ); } );
        add_filter( 'woocommerce_get_country_locale', static function ( $locale ) {
            if ( Compatibility::block_address_context() ) {
                $locale['VN']['city']['hidden'] = true;
                $locale['VN']['city']['required'] = false;
            }
            return $locale;
        }, 30 );
        ( new StoreApi() )->register();
    }
}
