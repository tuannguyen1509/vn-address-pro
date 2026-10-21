<?php
/** Run with wp eval-file; proves persistent lookups with every outbound request denied. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
require_once __DIR__ . '/Support/CacheState.php';
$vnap_state = new \Tunlamweb\VNAddressPro\Tests\Support\CacheState( array( null, 79 ) );
$vnap_state->clear();
$vnap_calls = 0;
$vnap_deny = static function () use ( &$vnap_calls ) { ++$vnap_calls; return new WP_Error( 'offline', 'All outgoing HTTP is blocked.' ); };
add_filter( 'pre_http_request', $vnap_deny, PHP_INT_MAX );
$vnap_error = null;
try {
    $local = new \Tunlamweb\VNAddressPro\Repository\LocalAddressRepository();
    if ( ! $local->has_usable_dataset() ) { throw new RuntimeException( 'Local dataset is not usable.' ); }
    foreach ( array( 'provinces', 'wards', 'province/79', 'health' ) as $path ) {
        $request = new WP_REST_Request( 'GET', '/vn-address/v1/' . $path );
        if ( $path === 'wards' ) { $request->set_param( 'province_code', 79 ); }
        $response = rest_get_server()->dispatch( $request );
        if ( $response->get_status() !== 200 || $response->get_data()['success'] !== true ) { throw new RuntimeException( 'Offline route failed: ' . $path ); }
        WP_CLI::line( 'PASS offline HTTP 200 ' . $path );
    }
    if ( $vnap_calls !== 0 ) { throw new RuntimeException( 'Healthy local lookups attempted outbound HTTP.' ); }
} catch ( Throwable $error ) { $vnap_error = $error->getMessage(); }
finally { remove_filter( 'pre_http_request', $vnap_deny, PHP_INT_MAX ); $vnap_state->restore(); }
if ( $vnap_error !== null ) { WP_CLI::error( $vnap_error ); }
WP_CLI::success( 'Offline local repository: four routes, zero outgoing HTTP.' );
