<?php
namespace Tunlamweb\VNAddressPro\Http;

defined( 'ABSPATH' ) || exit;

/** JSON transport only. URLs are supplied by trusted application code, never REST input. */
class HttpClient {
    public function get( string $url ): array|\WP_Error {
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 15,
                'redirection' => 3,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'User-Agent' => 'WordPress/VN-Address-API-2025',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'vnap_transport_error',
                __( 'The address data request failed.', 'vn-address-pro' ),
                array( 'detail' => $response->get_error_message() )
            );
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            return new \WP_Error(
                'vnap_remote_http_error',
                __( 'The address provider returned an HTTP error.', 'vn-address-pro' ),
                array( 'upstream_status' => $status )
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'vnap_invalid_payload', __( 'The address provider returned an invalid payload.', 'vn-address-pro' ) );
        }
        return $data;
    }
}
