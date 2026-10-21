<?php
/** Public legacy facade: preserve the class, constants and method signatures. */
defined( 'ABSPATH' ) || exit;

final class VN_Address_API_2025 {
    const NS = \Tunlamweb\VNAddressPro\Rest\V1\AddressController::NS;
    const UPSTREAM = \Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider::UPSTREAM;
    const CACHE_TTL = \Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider::CACHE_TTL;

    public static function init(): void {
        // Retain the existing hook identity for external remove_action()/has_action().
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        \Tunlamweb\VNAddressPro\Plugin::instance()->rest_controller()->register_routes();
    }

    public static function get_provinces(WP_REST_Request $request) {
        return \Tunlamweb\VNAddressPro\Plugin::instance()->rest_controller()->get_provinces( $request );
    }

    public static function get_wards(WP_REST_Request $request) {
        return \Tunlamweb\VNAddressPro\Plugin::instance()->rest_controller()->get_wards( $request );
    }

    public static function get_province(WP_REST_Request $request) {
        return \Tunlamweb\VNAddressPro\Plugin::instance()->rest_controller()->get_province( $request );
    }
}
