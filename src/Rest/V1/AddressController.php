<?php
namespace Tunlamweb\VNAddressPro\Rest\V1;

use Tunlamweb\VNAddressPro\Address\AddressService;
use Tunlamweb\VNAddressPro\Address\Validator;

defined( 'ABSPATH' ) || exit;

final class AddressController {
    public const NS = 'vn-address/v1';
    private ?\WP_REST_Server $registered_server = null;

    public function __construct( private AddressService $service ) {}

    public function register_routes(): void {
        $server = rest_get_server();
        if ( $this->registered_server === $server ) {
            return;
        }
        register_rest_route(
            self::NS,
            '/provinces',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_provinces' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'search' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                ),
            )
        );
        register_rest_route(
            self::NS,
            '/wards',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_wards' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'province_code' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( $value ) => absint( $value ) > 0,
                    ),
                    'search'        => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                ),
            )
        );
        register_rest_route(
            self::NS,
            '/province/(?P<code>\d+)',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_province' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'code' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
                ),
            )
        );
        register_rest_route(
            self::NS,
            '/health',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_health' ),
                'permission_callback' => '__return_true',
            )
        );
        $this->registered_server = $server;
    }

    public function get_provinces( \WP_REST_Request $request ) {
        $items = $this->service->get_provinces( Validator::normalize_v1_search( $request->get_param( 'search' ) ) );
        if ( is_wp_error( $items ) ) {
            return ErrorMapper::map( $items );
        }
        return rest_ensure_response( array( 'success' => true, 'count' => count( $items ), 'data' => $items ) );
    }

    public function get_wards( \WP_REST_Request $request ) {
        $result = $this->service->get_wards(
            Validator::normalize_v1_province_code( $request->get_param( 'province_code' ) ),
            Validator::normalize_v1_search( $request->get_param( 'search' ) )
        );
        if ( is_wp_error( $result ) ) {
            return ErrorMapper::map( $result );
        }
        return rest_ensure_response(
            array( 'success' => true, 'province' => $result['province'], 'count' => count( $result['wards'] ), 'data' => $result['wards'] )
        );
    }

    public function get_province( \WP_REST_Request $request ) {
        $province = $this->service->get_province( Validator::normalize_v1_province_code( $request['code'] ) );
        if ( is_wp_error( $province ) ) {
            return ErrorMapper::map( $province );
        }
        return rest_ensure_response( array( 'success' => true, 'data' => $province ) );
    }

    public function get_health() {
        return rest_ensure_response(
            array( 'success' => true, 'service' => 'VN Address API 2025+', 'version' => '1.0.0', 'administrative_model' => '2-level' )
        );
    }
}
