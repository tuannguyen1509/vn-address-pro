<?php
namespace Tunlamweb\VNAddressPro\Tests\Integration;

use Tunlamweb\VNAddressPro\Data\DatasetLoader;
use Tunlamweb\VNAddressPro\Data\DatasetValidator;
use Tunlamweb\VNAddressPro\Data\DatasetInstaller;
use Tunlamweb\VNAddressPro\Data\BundledDataset;
use Tunlamweb\VNAddressPro\Database\TableNames;
use Tunlamweb\VNAddressPro\Database\SchemaManager;
use Tunlamweb\VNAddressPro\Repository\LocalAddressRepository;
use Tunlamweb\VNAddressPro\Repository\FallbackAddressRepository;
use Tunlamweb\VNAddressPro\Repository\RemoteAddressRepository;
use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider;
use Tunlamweb\VNAddressPro\Http\HttpClient;
use Tunlamweb\VNAddressPro\Cache\AddressCache;
use Tunlamweb\VNAddressPro\Plugin;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
require_once dirname( __DIR__ ) . '/Support/CacheState.php';

$assert = static function ( bool $condition, string $message = 'Assertion failed' ): void {
    if ( ! $condition ) { throw new \RuntimeException( $message ); }
};
$passed = 0; $failures = array(); $http_calls = 0; $http_mode = 'blocked';
$fixture = json_decode( file_get_contents( dirname( __DIR__ ) . '/Contract/fixtures/rest-v1.json' ), true, 512, JSON_THROW_ON_ERROR );
$small = array( 'schema_version' => 1, 'dataset_version' => '20261021000001', 'provinces' => $fixture['cache_payloads'][0] );
foreach ( $small['provinces'] as &$province ) {
    $province['wards'] = $province['code'] === 1 ? $fixture['cache_payloads'][1]['wards'] : array();
}
unset( $province );
$tmp = get_temp_dir() . 'vnap-dataset-tests-' . wp_generate_uuid4();
wp_mkdir_p( $tmp );
$pack = static function ( array $data ) use ( $tmp ): DatasetLoader {
    $bytes = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
    $wards = array_sum( array_map( static fn( $p ) => count( $p['wards'] ), $data['provinces'] ) );
    $version = $data['dataset_version'];
    $manifest = array( 'schema_version' => $data['schema_version'], 'dataset_version' => $version,
        'source' => 'Deterministic test fixture', 'generated_at' => \DateTimeImmutable::createFromFormat( '!YmdHis', $version, new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ),
        'province_count' => count( $data['provinces'] ), 'ward_count' => $wards, 'checksum' => hash( 'sha256', $bytes ) );
    file_put_contents( $tmp . '/vn-address.json', $bytes );
    file_put_contents( $tmp . '/manifest.json', wp_json_encode( $manifest ) );
    return new DatasetLoader( $tmp );
};
$repository = new LocalAddressRepository(); $installer = new DatasetInstaller(); $validator = new DatasetValidator();
$state = new \Tunlamweb\VNAddressPro\Tests\Support\CacheState( array( null, 1, 79, 999999 ) );
$intercept = static function ( $pre, $args, $url ) use ( &$http_calls, &$http_mode, $fixture ) {
    ++$http_calls;
    if ( $http_mode === 'blocked' ) { return new \WP_Error( 'offline', 'Fixture transport failure' ); }
    return array( 'headers' => array(), 'body' => wp_json_encode( str_ends_with( $url, '/p/' ) ? $fixture['cache_payloads'][0] : $fixture['cache_payloads'][1] ), 'response' => array( 'code' => 200, 'message' => '' ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $intercept, PHP_INT_MAX, 3 );
add_filter( 'vnap_log_to_error_log', '__return_false' );
$test = static function ( string $name, callable $callback ) use ( &$passed, &$failures, &$http_calls, &$http_mode, $state ): void {
    $state->clear(); $http_calls = 0; $http_mode = 'blocked';
    try { $callback(); ++$passed; \WP_CLI::line( 'PASS ' . $name ); }
    catch ( \Throwable $error ) { $failures[] = $name . ': ' . $error->getMessage(); }
};
$dispatch = static function ( string $path, array $params = array() ) {
    $request = new \WP_REST_Request( 'GET', '/vn-address/v1/' . $path );
    $request->set_query_params( $params );
    return rest_get_server()->dispatch( $request );
};
set_error_handler( static function ( $severity, $message, $file, $line ) {
    if ( error_reporting() & $severity ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); }
    return false;
} );

try {
    $test( 'Bundle: checksum, versions, counts and canonical relationships', static function () use ( $assert, $repository ) {
        $package = ( new DatasetLoader() )->load();
        $assert( ! is_wp_error( $package ) && $repository->has_usable_dataset() );
        $assert( $package['manifest']['dataset_version'] === BundledDataset::VERSION && $package['manifest']['checksum'] === BundledDataset::CHECKSUM );
        foreach ( $package['dataset']['provinces'] as $province ) {
            foreach ( $province['wards'] as $ward ) { $assert( $ward['province_code'] === $province['code'] ); }
        }
    } );
    $test( 'Validator: valid canonical fixture', static function () use ( $assert, $validator, $small ) { $assert( $validator->validate( $small ) ); } );
    $invalid = array( 'root' => null );
    $bad = $small; $bad['schema_version'] = 2; $invalid['schema'] = $bad;
    $bad = $small; $bad['dataset_version'] = 'v1'; $invalid['version'] = $bad;
    $bad = $small; $bad['provinces'] = array(); $invalid['empty provinces'] = $bad;
    $bad = $small; $bad['provinces'][] = $bad['provinces'][0]; $invalid['duplicate province'] = $bad;
    $bad = $small; $bad['provinces'][1]['wards'][0]['province_code'] = 999999; $invalid['orphan ward'] = $bad;
    $bad = $small; $bad['provinces'][0]['wards'] = array( $bad['provinces'][1]['wards'][0] ); $bad['provinces'][0]['wards'][0]['province_code'] = $bad['provinces'][0]['code']; $invalid['global duplicate ward'] = $bad;
    $bad = $small; unset( $bad['provinces'][0]['name'] ); $invalid['missing field'] = $bad;
    $bad = $small; $bad['provinces'][0]['code'] = true; $invalid['scalar type'] = $bad;
    $bad = $small; $bad['provinces'][0]['name'] = "\xFF"; $invalid['invalid UTF-8'] = $bad;
    $bad = $small; $bad['provinces'][0]['name'] = str_repeat( 'Đ', 192 ); $invalid['oversize column value'] = $bad;
    foreach ( $invalid as $name => $value ) {
        $test( 'Validator rejects ' . $name, static function () use ( $assert, $validator, $value ) { $assert( ! $validator->validate( $value ) ); } );
    }
    $test( 'Loader rejects checksum corruption without changing database', static function () use ( $assert, $pack, $small, $tmp, $installer ) {
        $before = get_option( 'vnap_dataset_metadata' );
        $loader = $pack( $small ); file_put_contents( $tmp . '/vn-address.json', ' ', FILE_APPEND );
        $assert( is_wp_error( $loader->load() ) && is_wp_error( $installer->install( $loader, true ) ) );
        $assert( get_option( 'vnap_dataset_metadata' ) === $before );
    } );
    $test( 'Loader rejects manifest count mismatch', static function () use ( $assert, $pack, $small, $tmp ) {
        $loader = $pack( $small ); $m = json_decode( file_get_contents( $tmp . '/manifest.json' ), true );
        ++$m['ward_count']; file_put_contents( $tmp . '/manifest.json', wp_json_encode( $m ) );
        $assert( is_wp_error( $loader->load() ) );
    } );
    $test( 'Import fixture: canonical fields, phone_code and dataset marker', static function () use ( $assert, $installer, $pack, $small, $repository ) {
        $assert( $installer->install( $pack( $small ), true ) === true );
        $assert( $repository->has_usable_dataset() && get_option( 'vnap_dataset_version' ) === $small['dataset_version'] );
        $assert( $repository->get_province( 1 )['phone_code'] === 24 && $repository->get_province( 91 )['phone_code'] === null );
    } );
    $test( 'Repository: provinces one query, no ward loading', static function () use ( $assert, $repository ) {
        global $wpdb;
        $before = $wpdb->num_queries; $rows = $repository->get_provinces();
        $assert( count( $rows ) === 2 && $wpdb->num_queries - $before === 1 && ! isset( $rows[0]['wards'] ) );
    } );
    $test( 'Repository: province detail/wards bounded query and original order', static function () use ( $assert, $repository, $fixture ) {
        global $wpdb;
        $before = $wpdb->num_queries; $province = $repository->get_province( 1 );
        $assert( $wpdb->num_queries - $before === 1 && array_column( $province['wards'], 'code' ) === array_column( $fixture['cache_payloads'][1]['wards'], 'code' ) );
        $assert( $repository->get_wards( 1 ) === $province );
    } );
    $test( 'Repository: get ward by unique code and unknown ward/province', static function () use ( $assert, $repository ) {
        $ward = $repository->get_ward( 1 );
        $assert( $ward['code'] === 1 && $ward['province_code'] === 1 && is_string( $ward['name'] ) );
        $assert( $repository->get_ward( 999999 )->get_error_code() === 'vnap_not_found' && $repository->get_province( 999999 )->get_error_code() === 'vnap_not_found' );
    } );
    $public_cases = array(
        array( 'provinces', array(), 'provinces' ), array( 'provinces', array( 'search' => 'an giang' ), 'province_search' ),
        array( 'provinces', array( 'search' => 'ha noi' ), 'province_no_accent' ), array( 'wards', array( 'province_code' => 1 ), 'wards' ),
        array( 'wards', array( 'province_code' => 1, 'search' => 'XÃ' ), 'ward_search' ), array( 'province/1', array(), 'province' ),
        array( 'health', array(), 'health' ), array( 'wards', array(), 'missing_parameter' ),
        array( 'wards', array( 'province_code' => 0 ), 'invalid_parameter' ), array( 'wards', array( 'province_code' => -1 ), 'negative_parameter' ),
        array( 'wards', array( 'province_code' => 1, 'search' => 'zzzz' ), 'empty_search' ),
    );
    foreach ( $public_cases as list( $path, $params, $snapshot ) ) {
        $test( 'Local REST frozen result: ' . $snapshot, static function () use ( $assert, $dispatch, $path, $params, $snapshot, $fixture, &$http_calls ) {
            $result = $dispatch( $path, $params );
            $assert( array( 'status' => $result->get_status(), 'body' => $result->get_data() ) === $fixture[ $snapshot ] );
            $assert( $http_calls === 0 );
        } );
    }
    $test( 'Local unknown province: no remote attempt', static function () use ( $assert, $dispatch, &$http_calls ) {
        $result = $dispatch( 'province/999999' );
        $assert( $result->get_status() === 404 && $http_calls === 0 );
    } );
    $test( 'Repeat importer: no duplicates and no downgrade', static function () use ( $assert, $installer, $pack, $small, $repository ) {
        $assert( $installer->install( $pack( $small ) ) === true && count( $repository->get_provinces() ) === 2 );
        $older = $small; $older['dataset_version'] = '20261020000001';
        $assert( $installer->install( $pack( $older ) ) === true && get_option( 'vnap_dataset_version' ) === $small['dataset_version'] );
    } );
    $test( 'Dataset version upgrade publishes only verified new version', static function () use ( $assert, $installer, $pack, $small ) {
        $new = $small; $new['dataset_version'] = '20261021000002';
        $assert( $installer->install( $pack( $new ) ) === true && get_option( 'vnap_dataset_version' ) === $new['dataset_version'] );
    } );
    $test( 'Ward insert failure rolls back provinces, wards and metadata', static function () use ( $assert, $installer, $pack, $small, $repository ) {
        $before = $repository->get_province( 1 ); $metadata = get_option( 'vnap_dataset_metadata' );
        $new = $small; $new['dataset_version'] = '20261021000003'; $new['provinces'][1]['name'] = 'Fixture replacement';
        $table = TableNames::wards();
        $fail = static fn( $sql ) => str_starts_with( $sql, 'INSERT INTO ' . $table . ' (' ) ? 'INVALID SQL FOR INTENTIONAL IMPORT FAILURE' : $sql;
        add_filter( 'query', $fail );
        try { $result = $installer->install( $pack( $new ) ); } finally { remove_filter( 'query', $fail ); }
        $assert( is_wp_error( $result ) && $repository->get_province( 1 ) === $before && get_option( 'vnap_dataset_metadata' ) === $metadata && $repository->has_usable_dataset() );
    } );
    $test( 'Schema: per-site tables and required indexes', static function () use ( $assert ) {
        global $wpdb;
        $assert( TableNames::provinces() === $wpdb->prefix . 'vnap_provinces' && VNAP_DB_VERSION === SchemaManager::VERSION );
        $p = $wpdb->get_results( 'SHOW INDEX FROM ' . TableNames::provinces(), ARRAY_A );
        $w = $wpdb->get_results( 'SHOW INDEX FROM ' . TableNames::wards(), ARRAY_A );
        $assert( in_array( 'PRIMARY', array_column( $p, 'Key_name' ), true ) && in_array( 'province_position', array_column( $w, 'Key_name' ), true ) );
    } );
    $test( 'Missing local tables: remote fallback works; both failed remains controlled', static function () use ( $assert, &$http_mode, &$http_calls, $dispatch, $state ) {
        global $wpdb;
        $table = TableNames::wards(); $backup = $table . '_test_hold';
        $wpdb->query( "RENAME TABLE $table TO $backup" );
        try {
            $http_mode = 'valid';
            $assert( $dispatch( 'province/1' )->get_status() === 200 && $http_calls === 1 );
            $state->clear(); $http_mode = 'blocked';
            $error = $dispatch( 'province/1' );
            $assert( $error->get_status() === 502 && $error->get_data()['code'] === 'vn_address_upstream_error' );
        } finally { $wpdb->query( "RENAME TABLE $backup TO $table" ); }
    } );
    $test( 'Upgrade from Phase 3 metadata installs bundled dataset without activation', static function () use ( $assert, $repository ) {
        delete_option( 'vnap_db_version' ); delete_option( 'vnap_dataset_version' ); delete_option( 'vnap_dataset_metadata' );
        delete_transient( 'vnap_dataset_install_failure' );
        Plugin::instance()->maybe_upgrade();
        $assert( $repository->has_usable_dataset() && get_option( 'vnap_dataset_version' ) === BundledDataset::VERSION );
    } );
    $test( 'Current version checkpoint: no expensive queries or import', static function () use ( $assert, $installer ) {
        global $wpdb;
        $assert( $installer->ensure() === true ); $before = $wpdb->num_queries;
        $assert( $installer->ensure() === true && $wpdb->num_queries === $before );
    } );
    $test( 'Full bundled local data: offline three routes + health, no remote cache needed', static function () use ( $assert, $dispatch, &$http_calls ) {
        foreach ( array( array( 'provinces', array() ), array( 'wards', array( 'province_code' => 79 ) ), array( 'province/79', array() ), array( 'health', array() ) ) as list( $path, $params ) ) {
            $assert( $dispatch( $path, $params )->get_status() === 200 );
        }
        $assert( $http_calls === 0 );
    } );
    $test( 'Full bundle persistent repository: province/ward counts match manifest', static function () use ( $assert, $repository ) {
        $m = get_option( 'vnap_dataset_metadata' ); $count = 0;
        $provinces = $repository->get_provinces();
        foreach ( $provinces as $p ) { $count += count( $repository->get_wards( $p['code'] )['wards'] ); }
        $assert( count( $provinces ) === $m['province_count'] && $count === $m['ward_count'] );
    } );
} finally {
    // This suite intentionally replaces local data: restore the shipped snapshot, not a cache.
    $restored = $installer->install( null, true );
    restore_error_handler();
    remove_filter( 'pre_http_request', $intercept, PHP_INT_MAX );
    remove_filter( 'vnap_log_to_error_log', '__return_false' );
    $state->restore();
    foreach ( array( '/vn-address.json', '/manifest.json' ) as $file ) { if ( is_file( $tmp . $file ) ) { unlink( $tmp . $file ); } }
    rmdir( $tmp );
    if ( is_wp_error( $restored ) ) { $failures[] = 'Bundled dataset restore failed.'; }
}
if ( $failures ) { \WP_CLI::error( implode( "\n", $failures ) ); }
\WP_CLI::success( $passed . ' local dataset/repository tests passed; all HTTP mocked or blocked.' );
\WP_CLI::line( 'Bundle bytes=' . filesize( VNAP_PATH . 'data/vn-address.json' ) . '; process peak memory=' . memory_get_peak_usage( true ) );
