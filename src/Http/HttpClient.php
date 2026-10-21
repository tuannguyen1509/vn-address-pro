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
                array( 'detail' => $response->get_error_message(), 'classification' => 'transport' )
            );
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            $classification = $status === 404 ? 'http_404' : ( $status === 429 ? 'http_429' : ( $status >= 500 ? 'http_5xx' : 'http_error' ) );
            $data = array( 'upstream_status' => $status, 'classification' => $classification );
            $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
            if ( $status === 429 && is_string( $retry_after ) ) {
                $data['retry_after'] = substr( trim( $retry_after ), 0, 128 );
            }
            return new \WP_Error(
                'vnap_remote_http_error',
                __( 'The address provider returned an HTTP error.', 'vn-address-pro' ),
                $data
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'vnap_invalid_payload', __( 'The address provider returned an invalid payload.', 'vn-address-pro' ), array( 'classification' => json_last_error() === JSON_ERROR_NONE ? 'invalid_root' : 'invalid_json' ) );
        }
        return $data;
    }
}
