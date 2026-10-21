<?php
/** Optional remote path test; deliberately separate from local/offline acceptance. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
require_once __DIR__ . '/Support/CacheState.php';
$vnap_state = new \Tunlamweb\VNAddressPro\Tests\Support\CacheState( array( null, 79 ) );
$vnap_state->clear();
$vnap_provider = new \Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider(
    new \Tunlamweb\VNAddressPro\Http\HttpClient(), new \Tunlamweb\VNAddressPro\Cache\AddressCache()
);
$vnap_error = null;
try {
    foreach ( array( $vnap_provider->get_provinces(), $vnap_provider->get_wards( 79 ), $vnap_provider->get_province( 79 ) ) as $result ) {
        if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Optional remote smoke failed: ' . $result->get_error_code() ); }
    }
} catch ( Throwable $error ) { $vnap_error = $error->getMessage(); }
finally { $vnap_state->restore(); }
if ( $vnap_error !== null ) { WP_CLI::error( $vnap_error ); }
WP_CLI::success( 'Optional remote provider list/wards/detail smoke passed.' );
