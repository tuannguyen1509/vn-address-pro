<?php
/**
 * Run against an installed WordPress: wp eval-file <this-file> [capture].
 * The capture argument is only for recording the pre-refactor baseline.
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

// Fixtures deliberately use synthetic records, not a bundled administrative dataset.
$vnap_province = array(
    'name' => 'Thành phố Hà Nội', 'code' => 1, 'division_type' => 'ignored',
    'codename' => 'ha_noi', 'phone_code' => 24,
);
$vnap_other = array(
    'name' => 'Tỉnh An Giang', 'code' => 91, 'division_type' => 'ignored',
    'codename' => 'an_giang',
);
$vnap_detail = $vnap_province;
$vnap_detail['wards'] = array(
    array( 'code' => 2, 'name' => 'Xã B', 'division_type' => 'xã', 'codename' => 'xa_b', 'province_code' => 1 ),
    array( 'code' => 1, 'name' => 'Phường A', 'division_type' => 'phường', 'codename' => 'phuong_a', 'province_code' => 1 ),
);
$vnap_mode = 'success';
$vnap_calls = array();
$vnap_http = static function ( $pre, $request, $url ) use ( &$vnap_mode, &$vnap_calls, $vnap_province, $vnap_other, $vnap_detail ) {
    if ( strpos( $url, 'https://provinces.open-api.vn/api/v2/' ) !== 0 ) {
        return $pre;
    }
    $vnap_calls[] = array( 'url' => $url, 'timeout' => $request['timeout'], 'redirection' => $request['redirection'], 'headers' => $request['headers'] );
    if ( $vnap_mode === 'transport' ) {
        return new WP_Error( 'test_transport', 'Fixture transport failure' );
    }
    $payload = substr( $url, -3 ) === '/p/' ? array( $vnap_other, $vnap_province ) : $vnap_detail;
    if ( $vnap_mode === 'missing' ) {
        $payload = array();
    }
    return array(
        'headers' => array(),
        'body' => $vnap_mode === 'json' ? 'null' : wp_json_encode( $payload ),
        'response' => array( 'code' => $vnap_mode === 'http' ? 404 : 200, 'message' => '' ),
        'cookies' => array(),
    );
};
add_filter( 'pre_http_request', $vnap_http, 10, 3 );
$vnap_dispatch = static function ( $route, $params = array() ) {
    $request = new WP_REST_Request( 'GET', '/vn-address/v1' . $route );
    $request->set_query_params( $params );
    $response = rest_get_server()->dispatch( $request );
    return array( 'status' => $response->get_status(), 'body' => $response->get_data() );
};
$vnap_keys = array( 'vn_address_provinces_v2', 'vn_address_province_1_depth2' );
require_once dirname( __DIR__ ) . '/Support/CacheState.php';
$vnap_state = new \Tunlamweb\VNAddressPro\Tests\Support\CacheState();
$vnap_state->clear();
require_once dirname( __DIR__ ) . '/Support/LocalState.php';
$vnap_local_state = new \Tunlamweb\VNAddressPro\Tests\Support\LocalState();
$vnap_snapshots = array();
$vnap_failure = null;
try {
    $vnap_snapshots['provinces'] = $vnap_dispatch( '/provinces' );
    $vnap_snapshots['province_search'] = $vnap_dispatch( '/provinces', array( 'search' => 'an giang' ) );
    $vnap_snapshots['province_no_accent'] = $vnap_dispatch( '/provinces', array( 'search' => 'ha noi' ) );
    $vnap_snapshots['wards'] = $vnap_dispatch( '/wards', array( 'province_code' => 1 ) );
    $vnap_snapshots['ward_search'] = $vnap_dispatch( '/wards', array( 'province_code' => 1, 'search' => 'XÃ' ) );
    $vnap_snapshots['province'] = $vnap_dispatch( '/province/1' );
    $vnap_snapshots['health'] = $vnap_dispatch( '/health' );
    $vnap_snapshots['missing_parameter'] = $vnap_dispatch( '/wards' );
    $vnap_snapshots['invalid_parameter'] = $vnap_dispatch( '/wards', array( 'province_code' => 0 ) );
    $vnap_snapshots['negative_parameter'] = $vnap_dispatch( '/wards', array( 'province_code' => -1 ) );
    $vnap_snapshots['empty_search'] = $vnap_dispatch( '/wards', array( 'province_code' => 1, 'search' => 'zzzz' ) );
    $vnap_snapshots['cached_http_calls'] = $vnap_calls;
    $vnap_snapshots['cache_payloads'] = array_map( 'get_transient', $vnap_keys );
    foreach ( array( 'transport', 'http', 'json', 'missing' ) as $vnap_mode ) {
        $vnap_state->clear();
        $vnap_snapshots[ 'error_' . $vnap_mode ] = $vnap_dispatch( '/province/1' );
    }
    $routes = rest_get_server()->get_routes();
    foreach ( array( '/provinces', '/wards', '/province/(?P<code>\d+)', '/health' ) as $route ) {
        $endpoint = $routes[ '/vn-address/v1' . $route ][0];
        $vnap_snapshots['routes'][ $route ] = array(
            'methods' => $endpoint['methods'],
            'permission' => call_user_func( $endpoint['permission_callback'] ),
            'args' => array_keys( $endpoint['args'] ),
        );
    }
    if ( isset( $args[0] ) && $args[0] === 'capture' ) {
        WP_CLI::line( wp_json_encode( $vnap_snapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    } else {
        $expected = json_decode( file_get_contents( __DIR__ . '/fixtures/rest-v1.json' ), true, 512, JSON_THROW_ON_ERROR );
        foreach ( $expected as $name => $value ) {
            if ( ! array_key_exists( $name, $vnap_snapshots ) || $value !== $vnap_snapshots[ $name ] ) {
                throw new RuntimeException( 'REST v1 regression: ' . $name );
            }
        }
        if ( count( $expected ) !== count( $vnap_snapshots ) ) {
            throw new RuntimeException( 'Unexpected contract snapshot count.' );
        }
        WP_CLI::success( count( $expected ) . ' REST v1 baseline comparisons passed.' );
    }
} catch ( Throwable $vnap_error ) {
    $vnap_failure = $vnap_error->getMessage();
} finally {
    remove_filter( 'pre_http_request', $vnap_http, 10 );
    $vnap_state->restore();
    $vnap_local_state->restore();
}
if ( $vnap_failure !== null ) {
    WP_CLI::error( $vnap_failure );
}
