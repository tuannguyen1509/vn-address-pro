<?php
/** Run with WooCommerce deactivated on a disposable site. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\{Compatibility,WooCommerceIntegration};
if ( class_exists( 'WooCommerce' ) ) { WP_CLI::error( 'Deactivate WooCommerce before this test.' ); }
WooCommerceIntegration::load();
if ( has_filter( 'woocommerce_states' ) || Compatibility::supported() ) { WP_CLI::error( 'Absent Woo integration unexpectedly registered.' ); }
$request = new WP_REST_Request( 'GET', '/vn-address/v1/provinces' );
if ( rest_do_request( $request )->get_status() !== 200 || ! str_contains( do_shortcode( '[vn_address_fields]' ), 'data-vnap-address' ) ) { WP_CLI::error( 'Core or shortcode failed without Woo.' ); }
// Simulated unsupported runtime in this process only; no old plugin code is executed.
define( 'WC_VERSION', '8.1.0' ); WooCommerceIntegration::load();
if ( Compatibility::supported() || has_filter( 'woocommerce_states' ) ) { WP_CLI::error( 'Unsupported Woo registered integration hooks.' ); }
WP_CLI::success( 'Woo absent/unsupported: core REST/shortcode remain functional; integration stays inactive.' );
