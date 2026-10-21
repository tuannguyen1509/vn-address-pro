<?php
namespace Tunlamweb\VNAddressPro\Tests\Unit;

use Tunlamweb\VNAddressPro\Address\AddressService;
use Tunlamweb\VNAddressPro\Address\Normalizer;
use Tunlamweb\VNAddressPro\Address\Validator;
use Tunlamweb\VNAddressPro\Cache\AddressCache;
use Tunlamweb\VNAddressPro\Contracts\AddressProviderInterface;
use Tunlamweb\VNAddressPro\Contracts\CacheInterface;
use Tunlamweb\VNAddressPro\Http\HttpClient;
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider;
use Tunlamweb\VNAddressPro\Rest\V1\ErrorMapper;
use Tunlamweb\VNAddressPro\Support\Search;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

final class MemoryCache implements CacheInterface {
    public array $values = array();
    public array $writes = array();

    public function get( string $key ) {
        return $this->values[ $key ] ?? false;
    }

    public function set( string $key, $value, int $ttl ): bool {
        $this->values[ $key ] = $value;
        $this->writes[] = array( $key, $value, $ttl );
        return true;
    }

    public function delete( string $key ): bool {
        $exists = array_key_exists( $key, $this->values );
        unset( $this->values[ $key ] );
        return $exists;
    }
}

final class StubHttpClient extends HttpClient {
    public array $calls = array();
    public array|\WP_Error $result = array();

    public function get( string $url ): array|\WP_Error {
        $this->calls[] = $url;
        return $this->result;
    }
}

final class StubProvider implements AddressProviderInterface {
    public array|\WP_Error $provinces = array();
    public array|\WP_Error $detail = array();

    public function get_provinces(): array|\WP_Error {
        return $this->provinces;
    }

    public function get_province( int $province_code ): array|\WP_Error {
        return $this->detail;
    }

    public function get_wards( int $province_code ): array|\WP_Error {
        return $this->detail;
    }
}

$assert = static function ( bool $condition, string $message = 'Assertion failed' ): void {
    if ( ! $condition ) {
        throw new \RuntimeException( $message );
    }
};
$passed = 0;
$failures = array();
require_once dirname( __DIR__ ) . '/Support/CacheState.php';
$state = new \Tunlamweb\VNAddressPro\Tests\Support\CacheState();
$test = static function ( string $name, callable $callback ) use ( &$passed, &$failures, $state ): void {
    $state->clear();
    try {
        $callback();
        ++$passed;
        \WP_CLI::line( 'PASS ' . $name );
    } catch ( \Throwable $error ) {
        $failures[] = $name . ': ' . $error->getMessage();
    }
};
$fixture = json_decode( file_get_contents( dirname( __DIR__ ) . '/Contract/fixtures/rest-v1.json' ), true, 512, JSON_THROW_ON_ERROR );
$raw_list = $fixture['cache_payloads'][0];
$raw_detail = $fixture['cache_payloads'][1];
$normalizer = new Normalizer();
$search = new Search();
$validator = new Validator();
$response = static function ( int $status, string $body ): array {
    return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $status, 'message' => '' ), 'cookies' => array() );
};
$http_result = $response( 200, '[]' );
$http_calls = array();
// Intercept EVERY outbound call, not just expected URLs: the suite never uses internet.
$intercept = static function ( $pre, $args, $url ) use ( &$http_result, &$http_calls ) {
    $http_calls[] = array( $url, $args );
    return $http_result;
};
add_filter( 'pre_http_request', $intercept, PHP_INT_MAX, 3 );
$saved = array();
foreach ( array( 'vnap_unit_cache' ) as $key ) {
    $saved[ $key ] = get_transient( $key );
}
set_error_handler( static function ( $level, $message, $file, $line ) {
    if ( error_reporting() & $level ) {
        throw new \ErrorException( $message, 0, $level, $file, $line );
    }
    return false;
} );

try {
    $test( 'Normalizer: exact province mapping', static function () use ( $assert, $normalizer, $raw_list, $fixture ) {
        $assert( $normalizer->normalize_province( $raw_list[1] ) === $fixture['provinces']['body']['data'][0] );
    } );
    $test( 'Normalizer: province defaults and non-city classification', static function () use ( $assert, $normalizer ) {
        $item = $normalizer->normalize_province( array() );
        $assert( $item === array( 'code' => 0, 'name' => 'Tỉnh ', 'short_name' => '', 'division_type' => 'tỉnh', 'codename' => '', 'phone_code' => null ) );
    } );
    $test( 'Normalizer: prefix trim and case sensitivity', static function () use ( $assert, $normalizer ) {
        $assert( $normalizer->strip_prefix( '  Tỉnh An Giang  ' ) === 'An Giang' );
        $assert( $normalizer->strip_prefix( 'tỉnh An Giang' ) === 'tỉnh An Giang' );
    } );
    $test( 'Normalizer: ward defaults preserve missing parent as zero', static function () use ( $assert, $normalizer ) {
        $assert( $normalizer->normalize_ward( array() ) === array( 'code' => 0, 'name' => '', 'division_type' => '', 'codename' => '', 'province_code' => 0 ) );
    } );
    $test( 'Search: empty and case-insensitive substring', static function () use ( $assert, $search ) {
        $assert( $search->contains_ci( 'An Giang', '' ) && $search->contains_ci( 'An Giang', 'GIANG' ) );
    } );
    $test( 'Search: Unicode and accents remain unchanged', static function () use ( $assert, $search ) {
        $assert( $search->contains_ci( 'Xã B', 'XÃ' ) && ! $search->contains_ci( 'Hà Nội', 'ha noi' ) );
    } );
    $test( 'Validator: v1 permissive code and safe search coercions', static function () use ( $assert ) {
        $assert( Validator::normalize_v1_province_code( -1 ) === 1 );
        $assert( Validator::normalize_v1_province_code( '1suffix' ) === 1 );
        $assert( Validator::normalize_v1_search( null ) === '' );
        $assert( Validator::normalize_v1_search( array( 'bad' ) ) === '' );
        $assert( Validator::normalize_v1_search( ' A ' ) === 'A' );
    } );
    $test( 'Validator: optional fields and missing/null wards remain valid', static function () use ( $assert, $validator ) {
        $assert( $validator->provinces( array( array() ) ) );
        $assert( $validator->province( array( 'code' => 1 ) ) );
        $assert( $validator->province( array( 'code' => 1, 'wards' => null ) ) );
    } );
    $test( 'Validator: malformed records do not reach normalizer', static function () use ( $assert, $validator ) {
        $assert( ! $validator->provinces( array( 'bad' ) ) );
        $assert( ! $validator->provinces( array( 'name' => array( 'bad' ) ) ) );
        $assert( ! $validator->provinces( array( array( 'name' => array() ) ) ) );
        $assert( ! $validator->province( array( 'code' => 1, 'wards' => 'bad' ) ) );
        $assert( ! $validator->province( array( 'code' => 1, 'wards' => array( 'bad' ) ) ) );
    } );

    $mock = new StubProvider();
    $mock->provinces = $raw_list;
    $mock->detail = $raw_detail;
    $service = new AddressService( $mock, $normalizer, $validator, $search );
    $test( 'Service: province normalization and numeric sorting', static function () use ( $assert, $service, $fixture ) {
        $assert( $service->get_provinces() === $fixture['provinces']['body']['data'] );
    } );
    $test( 'Service: province search and no transliteration', static function () use ( $assert, $service, $fixture ) {
        $assert( $service->get_provinces( 'an giang' ) === $fixture['province_search']['body']['data'] );
        $assert( $service->get_provinces( 'ha noi' ) === array() );
    } );
    $test( 'Service: wards normalization and natural sorting', static function () use ( $assert, $service, $fixture ) {
        $assert( $service->get_wards( 1 )['wards'] === $fixture['wards']['body']['data'] );
    } );
    $test( 'Service: ward search and empty results', static function () use ( $assert, $service, $fixture ) {
        $assert( $service->get_wards( 1, 'XÃ' )['wards'] === $fixture['ward_search']['body']['data'] );
        $assert( $service->get_wards( 1, 'zzzz' )['wards'] === array() );
    } );
    $test( 'Service: detail preserves provider ward order', static function () use ( $assert, $service, $fixture ) {
        $assert( $service->get_province( 1 ) === $fixture['province']['body']['data'] );
    } );
    $test( 'Service: missing and null wards give empty list', static function () use ( $assert, $service, $mock, $raw_detail ) {
        $mock->detail = array( 'code' => 1 );
        $assert( $service->get_province( 1 )['ward_count'] === 0 );
        $mock->detail['wards'] = null;
        $assert( $service->get_wards( 1 )['wards'] === array() );
        $mock->detail = $raw_detail;
    } );
    $test( 'Service: missing code maps to not-found first', static function () use ( $assert, $service, $mock, $raw_detail ) {
        $mock->detail = array( 'wards' => 'bad' );
        $assert( $service->get_province( 1 )->get_error_code() === 'vnap_not_found' );
        $mock->detail = $raw_detail;
    } );
    $test( 'Service: errors propagate unchanged', static function () use ( $assert, $service, $mock, $raw_list, $raw_detail ) {
        $error = new \WP_Error( 'vnap_transport_error', 'test' );
        $mock->provinces = $error;
        $mock->detail = $error;
        $assert( $service->get_provinces() === $error && $service->get_province( 1 ) === $error && $service->get_wards( 1 ) === $error );
        $mock->provinces = $raw_list;
        $mock->detail = $raw_detail;
    } );
    $test( 'Service: malformed provider payload becomes safe error', static function () use ( $assert, $service, $mock, $raw_list, $raw_detail ) {
        $mock->provinces = array( 'name' => 'wrong-root' );
        $mock->detail = array( 'code' => 1, 'wards' => array( array( 'name' => array() ) ) );
        $assert( $service->get_provinces()->get_error_code() === 'vnap_invalid_payload' );
        $assert( $service->get_province( 1 )->get_error_code() === 'vnap_invalid_payload' );
        $mock->provinces = $raw_list;
        $mock->detail = $raw_detail;
    } );

    $cache = new AddressCache();
    $test( 'AddressCache: miss, set, hit and delete', static function () use ( $assert, $cache ) {
        $cache->delete( 'vnap_unit_cache' );
        $assert( $cache->get( 'vnap_unit_cache' ) === false );
        $assert( $cache->set( 'vnap_unit_cache', array( 'fixture' ), DAY_IN_SECONDS ) );
        $assert( $cache->get( 'vnap_unit_cache' ) === array( 'fixture' ) );
        $assert( $cache->delete( 'vnap_unit_cache' ) );
        $assert( $cache->get( 'vnap_unit_cache' ) === false );
    } );
    $test( 'AddressCache: empty array is hit and TTL remains 24 hours', static function () use ( $assert, $cache ) {
        $start = time();
        $cache->set( 'vnap_unit_cache', array(), DAY_IN_SECONDS );
        $assert( $cache->get( 'vnap_unit_cache' ) === array() );
        if ( ! wp_using_ext_object_cache() ) {
            $expires = (int) get_option( '_transient_timeout_vnap_unit_cache' );
            $assert( $expires >= $start + DAY_IN_SECONDS && $expires <= time() + DAY_IN_SECONDS );
        }
    } );
    $test( 'Provider: list cache miss uses exact URL/key/TTL and raw payload', static function () use ( $assert, $raw_list ) {
        $http = new StubHttpClient();
        $http->result = $raw_list;
        $memory = new MemoryCache();
        $provider = new ProvinceOpenApiProvider( $http, $memory );
        $assert( $provider->get_provinces() === $raw_list );
        $assert( $http->calls === array( ProvinceOpenApiProvider::UPSTREAM . '/p/' ) );
        $keys = ProvinceOpenApiProvider::runtime_keys();
        $assert( $memory->values[ $keys['active'] ] === $raw_list && $memory->values[ $keys['lkg'] ] === $raw_list );
        $assert( in_array( array( $keys['active'], $raw_list, DAY_IN_SECONDS ), $memory->writes, true ) );
    } );
    $test( 'Provider: existing legacy cache avoids HTTP and cache rewrite', static function () use ( $assert, $raw_list ) {
        $http = new StubHttpClient();
        $memory = new MemoryCache();
        $memory->values['vn_address_provinces_v2'] = $raw_list;
        $provider = new ProvinceOpenApiProvider( $http, $memory );
        $assert( $provider->get_provinces() === $raw_list && $http->calls === array() && $memory->writes === array() );
    } );
    $test( 'Provider: wards and detail share one remote request', static function () use ( $assert, $raw_detail ) {
        $http = new StubHttpClient();
        $http->result = $raw_detail;
        $memory = new MemoryCache();
        $provider = new ProvinceOpenApiProvider( $http, $memory );
        $assert( $provider->get_wards( 1 ) === $raw_detail && $provider->get_province( 1 ) === $raw_detail );
        $assert( $http->calls === array( ProvinceOpenApiProvider::UPSTREAM . '/p/1?depth=2' ) );
        $keys = ProvinceOpenApiProvider::runtime_keys( 1 );
        $assert( $memory->values[ $keys['active'] ] === $raw_detail && $memory->values[ $keys['lkg'] ] === $raw_detail );
    } );
    $test( 'Provider: empty detail never becomes active data', static function () use ( $assert ) {
        $http = new StubHttpClient();
        $memory = new MemoryCache();
        $provider = new ProvinceOpenApiProvider( $http, $memory );
        $assert( $provider->get_province( 1 )->get_error_code() === 'vnap_not_found' );
        $assert( $provider->get_province( 1 )->get_error_code() === 'vnap_not_found' );
        $keys = ProvinceOpenApiProvider::runtime_keys( 1 );
        $assert( ! isset( $memory->values[ $keys['active'] ] ) && ! isset( $memory->values[ $keys['lkg'] ] ) );
        $assert( count( $http->calls ) === 1 );
    } );
    $test( 'Provider: failures use short markers, never active or LKG', static function () use ( $assert ) {
        $http = new StubHttpClient();
        $http->result = new \WP_Error( 'vnap_transport_error', 'test' );
        $memory = new MemoryCache();
        $provider = new ProvinceOpenApiProvider( $http, $memory );
        $assert( $provider->get_provinces()->get_error_code() === 'vnap_transport_error' );
        $assert( $provider->get_provinces()->get_error_code() === 'vnap_transport_error' );
        $keys = ProvinceOpenApiProvider::runtime_keys();
        $assert( count( $http->calls ) === 2 && isset( $memory->values[ $keys['failure'] ] ) );
        $assert( ! isset( $memory->values[ $keys['active'] ] ) && ! isset( $memory->values[ $keys['lkg'] ] ) );
    } );
    $test( 'Provider: corrupt active value is removed before refresh', static function () use ( $assert ) {
        $memory = new MemoryCache();
        $memory->values['vn_address_provinces_v2'] = 'bad';
        $http = new StubHttpClient();
        $provider = new ProvinceOpenApiProvider( $http, $memory );
        $assert( $provider->get_provinces()->get_error_code() === 'vnap_invalid_payload' && count( $http->calls ) === 1 );
        $assert( ! isset( $memory->values['vn_address_provinces_v2'] ) );
    } );

    $client = new HttpClient();
    $url = ProvinceOpenApiProvider::UPSTREAM . '/p/';
    $test( 'HttpClient: 200 JSON and frozen HTTP options', static function () use ( $assert, $client, $url, $response, &$http_result, &$http_calls, $raw_list ) {
        $http_result = $response( 200, wp_json_encode( $raw_list ) );
        $assert( $client->get( $url ) === $raw_list );
        $call = end( $http_calls );
        $assert( $call[0] === $url && $call[1]['timeout'] === 15 && $call[1]['redirection'] === 3 );
        $assert( $call[1]['headers'] === array( 'Accept' => 'application/json', 'User-Agent' => 'WordPress/VN-Address-API-2025' ) );
    } );
    $test( 'HttpClient: WP_Error transport maps exactly to v1', static function () use ( $assert, $client, $url, &$http_result, $fixture ) {
        $http_result = new \WP_Error( 'mock_transport', 'Fixture transport failure' );
        $error = $client->get( $url );
        $assert( $error->get_error_code() === 'vnap_transport_error' );
        $mapped = rest_convert_error_to_response( ErrorMapper::map( $error ) );
        $assert( array( 'status' => $mapped->get_status(), 'body' => $mapped->get_data() ) === $fixture['error_transport'] );
    } );
    foreach ( array( 404, 429, 500 ) as $status ) {
        $test( 'HTTP ' . $status . ': provider and v1 mapping remain 502 without fallback', static function () use ( $assert, $status, $client, $response, &$http_result ) {
            $http_result = $response( $status, '{"error":"fixture"}' );
            $memory = new MemoryCache();
            $provider = new ProvinceOpenApiProvider( $client, $memory );
            $error = $provider->get_provinces();
            $assert( $error->get_error_code() === 'vnap_remote_http_error' );
            $keys = ProvinceOpenApiProvider::runtime_keys();
            $assert( ! isset( $memory->values[ $keys['active'] ] ) && ! isset( $memory->values[ $keys['lkg'] ] ) && ! isset( $memory->values[ $keys['negative'] ] ) );
            $mapped = ErrorMapper::map( $error );
            $assert( $mapped->get_error_code() === 'vn_address_upstream_http_error' );
            $assert( $mapped->get_error_data() === array( 'status' => 502, 'upstream_status' => $status ) );
        } );
    }
    foreach ( array( '{broken', 'null', '"text"' ) as $body ) {
        $test( 'HttpClient: invalid JSON/root ' . $body, static function () use ( $assert, $body, $client, $url, $response, &$http_result ) {
            $http_result = $response( 200, $body );
            $error = $client->get( $url );
            $assert( $error->get_error_code() === 'vnap_invalid_payload' );
            $assert( ErrorMapper::map( $error )->get_error_data() === array( 'status' => 502 ) );
        } );
    }
    $test( 'Provider/service: unexpected 200 structure becomes mapped safe error', static function () use ( $assert, $client, $response, &$http_result, $normalizer, $validator, $search ) {
        $http_result = $response( 200, '{"name":["bad"]}' );
        $provider = new ProvinceOpenApiProvider( $client, new MemoryCache() );
        $service = new AddressService( $provider, $normalizer, $validator, $search );
        $error = $service->get_provinces();
        $assert( $error->get_error_code() === 'vnap_invalid_payload' );
        $assert( ErrorMapper::map( $error )->get_error_code() === 'vn_address_invalid_json' );
    } );
    $test( 'ErrorMapper: not-found and unknown errors do not expose internal messages', static function () use ( $assert ) {
        $assert( ErrorMapper::map( new \WP_Error( 'vnap_not_found', 'internal' ) )->get_error_data() === array( 'status' => 404 ) );
        $mapped = ErrorMapper::map( new \WP_Error( 'unknown', 'internal secret' ) );
        $assert( $mapped->get_error_code() === 'vn_address_invalid_json' && strpos( $mapped->get_error_message(), 'secret' ) === false );
    } );

    foreach ( array( 'get_provinces' => 'provinces', 'get_wards' => 'wards', 'get_province' => 'province' ) as $method => $snapshot ) {
        $test( 'Legacy: direct ' . $method . ' output and signature', static function () use ( $assert, $method, $snapshot, $fixture ) {
            set_transient( 'vn_address_provinces_v2', $fixture['cache_payloads'][0], DAY_IN_SECONDS );
            set_transient( 'vn_address_province_1_depth2', $fixture['cache_payloads'][1], DAY_IN_SECONDS );
            $reflection = new \ReflectionMethod( 'VN_Address_API_2025', $method );
            $assert( $reflection->isPublic() && $reflection->isStatic() && $reflection->getNumberOfParameters() === 1 );
            $assert( (string) $reflection->getParameters()[0]->getType() === 'WP_REST_Request' && $reflection->getReturnType() === null );
            $request = new \WP_REST_Request( 'GET' );
            $request->set_param( 'code', 1 );
            $request->set_param( 'province_code', 1 );
            $result = \VN_Address_API_2025::$method( $request );
            $assert( array( 'status' => $result->get_status(), 'body' => $result->get_data() ) === $fixture[ $snapshot ] );
        } );
    }
    $test( 'Legacy: constants, single hook and idempotent route registration', static function () use ( $assert ) {
        $assert( \VN_Address_API_2025::NS === 'vn-address/v1' );
        $assert( \VN_Address_API_2025::UPSTREAM === ProvinceOpenApiProvider::UPSTREAM && \VN_Address_API_2025::CACHE_TTL === DAY_IN_SECONDS );
        $before = rest_get_server()->get_routes();
        \VN_Address_API_2025::init();
        \VN_Address_API_2025::init();
        \VN_Address_API_2025::register_routes();
        \VN_Address_API_2025::register_routes();
        $assert( $before === rest_get_server()->get_routes() && did_action( 'vnap_loaded' ) === 1 );
        $assert( Plugin::instance()->address_service() === Plugin::instance()->address_service() );
    } );
    $test( 'Controller: health is exact legacy payload with no provider call', static function () use ( $assert, $fixture, &$http_calls ) {
        $count = count( $http_calls );
        $result = Plugin::instance()->rest_controller()->get_health();
        $assert( array( 'status' => $result->get_status(), 'body' => $result->get_data() ) === $fixture['health'] );
        $assert( count( $http_calls ) === $count );
    } );
    $test( 'Controller: direct non-scalar search causes no warning', static function () use ( $assert, $fixture ) {
        set_transient( 'vn_address_provinces_v2', $fixture['cache_payloads'][0], DAY_IN_SECONDS );
        $request = new \WP_REST_Request( 'GET' );
        $request->set_param( 'search', array( 'bad' ) );
        $assert( \VN_Address_API_2025::get_provinces( $request )->get_data() === $fixture['provinces']['body'] );
    } );
} finally {
    restore_error_handler();
    remove_filter( 'pre_http_request', $intercept, PHP_INT_MAX );
    $state->restore();
    foreach ( $saved as $key => $value ) {
        delete_transient( $key );
        if ( $value !== false ) {
            set_transient( $key, $value, DAY_IN_SECONDS );
        }
    }
}
if ( $failures ) {
    \WP_CLI::error( implode( "\n", $failures ) );
}
\WP_CLI::success( $passed . ' core tests passed; all HTTP was mocked.' );
