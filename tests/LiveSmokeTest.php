<?php
/** Optional live HTTP/provider smoke check. Never part of the offline core suite. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

$vnap_expect = static function ( bool $condition, string $message ): void {
    if ( ! $condition ) {
        WP_CLI::error( $message );
    }
};
$vnap_cases = array(
    'provinces' => array( 'success', 'count', 'data' ),
    'wards' => array( 'success', 'province', 'count', 'data' ),
    'province/79' => array( 'success', 'data' ),
    'health' => array( 'success', 'service', 'version', 'administrative_model' ),
);
foreach ( $vnap_cases as $route => $keys ) {
    $url = rest_url( 'vn-address/v1/' . $route );
    if ( $route === 'wards' ) {
        $url = add_query_arg( 'province_code', 79, $url );
    }
    $response = wp_remote_get( $url, array( 'timeout' => 40 ) );
    $vnap_expect( ! is_wp_error( $response ), 'Live HTTP transport failed: ' . $route );
    $vnap_expect( wp_remote_retrieve_response_code( $response ) === 200, 'Live HTTP status failed: ' . $route );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $vnap_expect( is_array( $body ) && array_keys( $body ) === $keys && $body['success'] === true, 'Live response schema failed: ' . $route );
    if ( $route === 'provinces' || $route === 'wards' ) {
        $vnap_expect( is_array( $body['data'] ) && is_int( $body['count'] ) && $body['count'] === count( $body['data'] ), 'Live list types failed.' );
        foreach ( $body['data'] as $record ) {
            $vnap_expect( is_int( $record['code'] ) && is_string( $record['name'] ) && is_string( $record['codename'] ) && is_string( $record['division_type'] ), 'Live record types failed.' );
            if ( $route === 'wards' ) {
                $vnap_expect( is_int( $record['province_code'] ), 'Live ward parent type failed.' );
            }
        }
    }
    if ( $route === 'province/79' ) {
        $province = $body['data'];
        $vnap_expect( is_int( $province['code'] ) && is_array( $province['wards'] ) && is_int( $province['ward_count'] ) && $province['ward_count'] === count( $province['wards'] ), 'Live detail types failed.' );
    }
    if ( $route === 'health' ) {
        $vnap_expect( $body === array( 'success' => true, 'service' => 'VN Address API 2025+', 'version' => '1.0.0', 'administrative_model' => '2-level' ), 'Live health payload changed.' );
    }
    WP_CLI::line( 'PASS HTTP 200 ' . $route );
}
WP_CLI::success( 'Four live REST v1 endpoints passed.' );
