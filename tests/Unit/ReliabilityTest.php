<?php
namespace Tunlamweb\VNAddressPro\Tests\Reliability;

use Tunlamweb\VNAddressPro\Cache\RefreshLock;
use Tunlamweb\VNAddressPro\Contracts\CacheInterface;
use Tunlamweb\VNAddressPro\Http\HttpClient;
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider as Provider;
use Tunlamweb\VNAddressPro\Providers\ReliabilityPolicy;
use Tunlamweb\VNAddressPro\Providers\Validation\ProvinceOpenApiValidator;
use Tunlamweb\VNAddressPro\Rest\V1\ErrorMapper;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}
require_once dirname( __DIR__ ) . '/Support/CacheState.php';

final class RuntimeCache implements CacheInterface {
    public array $values = array();
    public array $expires = array();
    public array $writes = array();
    public array $fail_keys = array();
    public float $now = 1000;

    public function get( string $key ) {
        if ( isset( $this->expires[ $key ] ) && $this->expires[ $key ] <= $this->now ) {
            $this->delete( $key );
        }
        return $this->values[ $key ] ?? false;
    }

    public function set( string $key, $value, int $ttl ): bool {
        $this->writes[] = array( $key, $ttl );
        if ( in_array( $key, $this->fail_keys, true ) ) {
            return false;
        }
        $this->values[ $key ] = $value;
        $this->expires[ $key ] = $this->now + $ttl;
        return true;
    }

    public function delete( string $key ): bool {
        $exists = isset( $this->values[ $key ] );
        unset( $this->values[ $key ], $this->expires[ $key ] );
        return $exists;
    }
}

final class QueueHttp extends HttpClient {
    public int $calls = 0;
    public ?\Closure $on_call = null;

    public function __construct( public array $results ) {}

    public function get( string $url ): array|\WP_Error {
        ++$this->calls;
        if ( $this->on_call ) {
            ( $this->on_call )();
        }
        return $this->results[ min( $this->calls - 1, count( $this->results ) - 1 ) ];
    }
}

final class TestLock extends RefreshLock {
    public bool $available = true;
    public int $releases = 0;

    public function acquire( string $resource, int $ttl ): ?string {
        if ( ! $this->available ) {
            return null;
        }
        $this->available = false;
        return 'fixture-lease';
    }

    public function release( string $resource, string $lease ): void {
        ++$this->releases;
        $this->available = true;
    }
}

$fixture = json_decode( file_get_contents( dirname( __DIR__ ) . '/Contract/fixtures/rest-v1.json' ), true, 512, JSON_THROW_ON_ERROR );
$list = $fixture['cache_payloads'][0];
$detail = $fixture['cache_payloads'][1];
$keys = Provider::runtime_keys();
$detail_keys = Provider::runtime_keys( 1 );
$validator = new ProvinceOpenApiValidator();
$state = new \Tunlamweb\VNAddressPro\Tests\Support\CacheState();
$assert = static function ( bool $value, string $message = 'Assertion failed' ): void {
    if ( ! $value ) {
        throw new \RuntimeException( $message );
    }
};
$passed = 0;
$failed = array();
$events = array();
$test = static function ( string $name, callable $run ) use ( &$passed, &$failed, &$events, $state ): void {
    $state->clear();
    $events = array();
    try {
        $run();
        ++$passed;
        \WP_CLI::line( 'PASS ' . $name );
    } catch ( \Throwable $error ) {
        $failed[] = $name . ': ' . $error->getMessage();
    } finally {
        $state->clear();
    }
};
$make = static function ( array $responses ) {
    $cache = new RuntimeCache();
    $http = new QueueHttp( $responses );
    $lock = new TestLock();
    $provider = new Provider( $http, $cache, lock: $lock, clock: static fn() => $cache->now );
    return array( $provider, $cache, $http, $lock );
};
$http_error = static fn( int $status, string $retry = '' ) => new \WP_Error(
    'vnap_remote_http_error', 'Fixture HTTP error', array( 'upstream_status' => $status, 'retry_after' => $retry )
);
$transport = new \WP_Error( 'vnap_transport_error', 'Fixture failure', array( 'detail' => 'Fixture transport failure' ) );
$http_mode = 'valid';
$http_count = 0;
$response = static function ( int $status, string $body, array $headers = array() ): array {
    return array( 'headers' => $headers, 'body' => $body, 'response' => array( 'code' => $status, 'message' => '' ), 'cookies' => array() );
};
$intercept = static function () use ( &$http_mode, &$http_count, $list, $detail, $response, $transport ) {
    ++$http_count;
    if ( $http_mode === 'failure' ) {
        return $transport;
    }
    if ( is_array( $http_mode ) ) {
        return $http_mode;
    }
    // URL is the third pre_http_request argument; all requests are intercepted.
    $args = func_get_args();
    return $response( 200, wp_json_encode( str_ends_with( $args[2], '/p/' ) ? $list : $detail ) );
};
$diagnostic = static function ( $event, $context ) use ( &$events ) { $events[] = array( $event, $context ); };
add_filter( 'pre_http_request', $intercept, PHP_INT_MAX, 3 );
add_filter( 'vnap_log_to_error_log', '__return_false' );
add_action( 'vnap_diagnostic', $diagnostic, 10, 2 );
set_error_handler( static function ( $level, $message, $file, $line ) {
    if ( error_reporting() & $level ) {
        throw new \ErrorException( $message, 0, $level, $file, $line );
    }
    return false;
} );

try {
    $test( 'Validation: valid list, no fixed total', static function () use ( $assert, $validator, $list ) {
        $assert( $validator->validate( $list ) && $validator->validate( array( $list[0] ) ) );
    } );
    $invalid_lists = array(
        'empty' => array(), 'duplicate' => array( $list[0], $list[0] ),
        'missing code' => array( array( 'name' => 'Fixture' ) ),
        'missing name' => array( array( 'code' => 1 ) ),
        'empty name' => array( array( 'code' => 1, 'name' => ' ' ) ),
        'boolean code' => array( array( 'code' => true, 'name' => 'Fixture' ) ),
        'array code' => array( array( 'code' => array( 1 ), 'name' => 'Fixture' ) ),
        'overflow code' => array( array( 'code' => str_repeat( '9', 50 ), 'name' => 'Fixture' ) ),
    );
    foreach ( $invalid_lists as $name => $invalid ) {
        $test( 'Validation/cache: reject province list ' . $name, static function () use ( $assert, $make, $invalid, $keys, $validator ) {
            $assert( ! $validator->validate( $invalid ) );
            list( $provider, $cache ) = $make( array( $invalid ) );
            $assert( is_wp_error( $provider->get_provinces() ) );
            $assert( $cache->get( $keys['active'] ) === false && $cache->get( $keys['lkg'] ) === false );
        } );
    }
    $test( 'Validation: matching detail and numeric-string code', static function () use ( $assert, $validator, $detail ) {
        $detail['code'] = '01';
        $assert( $validator->validate( $detail, 1 ) );
    } );
    $invalid_details = array();
    $bad = $detail; $bad['code'] = 79; $invalid_details['province mismatch'] = $bad;
    $bad = $detail; unset( $bad['name'] ); $invalid_details['missing province name'] = $bad;
    $bad = $detail; unset( $bad['wards'] ); $invalid_details['missing wards'] = $bad;
    $bad = $detail; $bad['wards'] = null; $invalid_details['null wards'] = $bad;
    $bad = $detail; $bad['wards'] = 'bad'; $invalid_details['ward collection type'] = $bad;
    $bad = $detail; $bad['wards'][0]['code'] = 0; $invalid_details['invalid ward code'] = $bad;
    $bad = $detail; $bad['wards'][0]['name'] = array(); $invalid_details['invalid ward name'] = $bad;
    $bad = $detail; $bad['wards'][0]['province_code'] = 79; $invalid_details['ward parent mismatch'] = $bad;
    $bad = $detail; $bad['wards'][] = $bad['wards'][0]; $invalid_details['duplicate ward'] = $bad;
    foreach ( $invalid_details as $name => $invalid ) {
        $test( 'Validation/cache: reject detail ' . $name, static function () use ( $assert, $make, $invalid, $detail_keys, $validator ) {
            $assert( ! $validator->validate( $invalid, 1 ) );
            list( $provider, $cache ) = $make( array( $invalid ) );
            $assert( is_wp_error( $provider->get_province( 1 ) ) );
            $assert( $cache->get( $detail_keys['active'] ) === false && $cache->get( $detail_keys['lkg'] ) === false );
        } );
    }
    $test( 'Validation: empty wards valid, optional parent/codename defaults retained', static function () use ( $assert, $validator, $detail ) {
        unset( $detail['codename'], $detail['phone_code'], $detail['wards'][0]['province_code'] );
        $assert( $validator->validate( $detail, 1 ) );
        $detail['wards'] = array();
        $assert( $validator->validate( $detail, 1 ) );
    } );
    $test( 'Cache: valid active avoids HTTP and writes', static function () use ( $assert, $make, $list, $keys ) {
        list( $provider, $cache, $http ) = $make( array( $list ) );
        $cache->values[ $keys['active'] ] = $list;
        $assert( $provider->get_provinces() === $list && $http->calls === 0 && $cache->writes === array() );
    } );
    $test( 'Cache: invalid active refreshed into validated active and LKG', static function () use ( $assert, $make, $list, $keys ) {
        list( $provider, $cache, $http ) = $make( array( $list ) );
        $cache->values[ $keys['active'] ] = array();
        $assert( $provider->get_provinces() === $list && $http->calls === 1 );
        $assert( $cache->get( $keys['active'] ) === $list && $cache->get( $keys['lkg'] ) === $list );
        $assert( $cache->expires[ $keys['lkg'] ] > $cache->expires[ $keys['active'] ] );
    } );
    foreach ( array( 'active', 'lkg' ) as $kind ) {
        $test( 'Cache: ' . $kind . ' write failure does not fail valid response', static function () use ( $assert, $make, $list, $keys, $kind, &$events ) {
            list( $provider, $cache ) = $make( array( $list ) );
            $cache->fail_keys[] = $keys[ $kind ];
            $assert( $provider->get_provinces() === $list );
            $assert( in_array( 'cache_write_failure', array_column( $events, 0 ), true ) );
        } );
    }
    $test( 'LKG: invalid remote never overwrites good data or extends retention', static function () use ( $assert, $make, $list, $keys ) {
        list( $provider, $cache ) = $make( array( $list, array() ) );
        $provider->get_provinces();
        $expires = $cache->expires[ $keys['lkg'] ];
        $cache->now += DAY_IN_SECONDS + 1;
        $assert( $provider->get_provinces() === $list );
        $assert( $cache->get( $keys['active'] ) === false && $cache->expires[ $keys['lkg'] ] === $expires );
    } );
    $test( 'LKG: corrupt backup ignored, not served', static function () use ( $assert, $make, $keys, $transport ) {
        list( $provider, $cache ) = $make( array( $transport ) );
        $cache->values[ $keys['lkg'] ] = array();
        $assert( is_wp_error( $provider->get_provinces() ) && $cache->get( $keys['lkg'] ) === false );
    } );
    $outages = array(
        'timeout' => $transport,
        'DNS' => new \WP_Error( 'vnap_transport_error', 'Fixture DNS failure' ),
        '500' => $http_error( 500 ), '503' => $http_error( 503 ), '429' => $http_error( 429, '120' ),
        'invalid JSON' => new \WP_Error( 'vnap_invalid_payload', 'Fixture invalid JSON', array( 'classification' => 'invalid_json' ) ),
        'invalid schema' => array(),
    );
    foreach ( $outages as $name => $outage ) {
        $test( 'Outage: expired active + ' . $name . ' returns LKG without retry', static function () use ( $assert, $make, $list, $keys, $outage ) {
            list( $provider, $cache, $http, $lock ) = $make( array( $list, $outage ) );
            $provider->get_provinces();
            $cache->now += DAY_IN_SECONDS + 1;
            $assert( $provider->get_provinces() === $list && $http->calls === 2 && $lock->releases === 2 );
            $assert( $cache->get( $keys['active'] ) === false && $cache->get( $keys['lkg'] ) === $list );
            $assert( $provider->get_provinces() === $list && $http->calls === 2 );
        } );
    }
    $test( 'Failure: without LKG preserves legacy transport response', static function () use ( $assert, $make, $transport, $fixture ) {
        list( $provider ) = $make( array( $transport ) );
        $result = rest_convert_error_to_response( ErrorMapper::map( $provider->get_province( 1 ) ) );
        $assert( array( 'status' => $result->get_status(), 'body' => $result->get_data() ) === $fixture['error_transport'] );
    } );
    $test( 'Failure: short backoff expires then refresh succeeds', static function () use ( $assert, $make, $http_error, $list, $keys ) {
        list( $provider, $cache, $http ) = $make( array( $http_error( 500 ), $http_error( 500 ), $list ) );
        $assert( is_wp_error( $provider->get_provinces() ) && $http->calls === 2 );
        $assert( is_wp_error( $provider->get_provinces() ) && $http->calls === 2 );
        $cache->now += 31;
        $assert( $provider->get_provinces() === $list && $http->calls === 3 );
        $assert( $cache->get( $keys['failure'] ) === false );
    } );
    $test( 'Negative: detail 404 is cached briefly, no retry, same legacy 502', static function () use ( $assert, $make, $http_error, $detail_keys ) {
        list( $provider, $cache, $http ) = $make( array( $http_error( 404 ) ) );
        $first = $provider->get_province( 1 );
        $second = $provider->get_province( 1 );
        $assert( $http->calls === 1 && $cache->get( $detail_keys['negative'] ) !== false );
        $assert( ErrorMapper::map( $first )->get_error_data() === array( 'status' => 502, 'upstream_status' => 404 ) );
        $assert( $second->get_error_data() === $first->get_error_data() );
        $cache->now += 601;
        $provider->get_province( 1 );
        $assert( $http->calls === 2 );
    } );
    $test( 'Negative: HTTP 500 never becomes not-found', static function () use ( $assert, $make, $http_error, $detail_keys ) {
        list( $provider, $cache ) = $make( array( $http_error( 500 ) ) );
        $provider->get_province( 1 );
        $assert( $cache->get( $detail_keys['negative'] ) === false && $cache->get( $detail_keys['failure'] ) !== false );
    } );
    $test( 'Retry: fast 503 then 200 updates active and LKG', static function () use ( $assert, $make, $http_error, $list, $keys ) {
        list( $provider, $cache, $http ) = $make( array( $http_error( 503 ), $list ) );
        $assert( $provider->get_provinces() === $list && $http->calls === 2 );
        $assert( $cache->get( $keys['active'] ) === $list && $cache->get( $keys['lkg'] ) === $list );
    } );
    $test( 'Retry: slow timeout is not doubled', static function () use ( $assert, $make, $transport ) {
        list( $provider, $cache, $http ) = $make( array( $transport ) );
        $http->on_call = static function () use ( $cache ) { $cache->now += 15; };
        $assert( is_wp_error( $provider->get_provinces() ) && $http->calls === 1 );
    } );
    foreach ( array( 400, 404, 429 ) as $status ) {
        $test( 'Retry: never synchronously retry ' . $status, static function () use ( $assert, $make, $http_error, $status ) {
            list( $provider, $cache, $http ) = $make( array( $http_error( $status ) ) );
            $provider->get_province( 1 );
            $assert( $http->calls === 1 );
        } );
    }
    $test( 'Lock: contention serves LKG without HTTP', static function () use ( $assert, $make, $list, $keys ) {
        list( $provider, $cache, $http, $lock ) = $make( array( $list ) );
        $cache->values[ $keys['lkg'] ] = $list;
        $lock->available = false;
        $assert( $provider->get_provinces() === $list && $http->calls === 0 && $lock->releases === 0 );
    } );
    $test( 'Lock: contention without fallback is controlled error, no HTTP', static function () use ( $assert, $make, $list ) {
        list( $provider, $cache, $http, $lock ) = $make( array( $list ) );
        $lock->available = false;
        $error = $provider->get_provinces();
        $assert( $error->get_error_code() === 'vnap_cache_lock' && $http->calls === 0 );
        $assert( ErrorMapper::map( $error )->get_error_data() === array( 'status' => 502 ) );
    } );
    $test( 'Lock: success and failure always release ownership', static function () use ( $assert, $make, $list, $transport ) {
        foreach ( array( $list, $transport ) as $value ) {
            list( $provider, $cache, $http, $lock ) = $make( array( $value ) );
            $provider->get_provinces();
            $assert( $lock->available && $lock->releases === 1 );
        }
    } );
    $test( 'Lock: unexpected exception still releases lease', static function () use ( $assert, $make, $list ) {
        list( $provider, $cache, $http, $lock ) = $make( array( $list ) );
        $http->on_call = static function () { throw new \RuntimeException( 'Fixture worker failure' ); };
        try { $provider->get_provinces(); } catch ( \RuntimeException $error ) {}
        $assert( $lock->available && $lock->releases === 1 );
    } );
    $test( 'Lock DB: exclusive acquisition and ownership-safe release', static function () use ( $assert, $keys ) {
        $one = new RefreshLock(); $two = new RefreshLock();
        $lease = $one->acquire( $keys['active'], 30 );
        $assert( is_string( $lease ) && $two->acquire( $keys['active'], 30 ) === null );
        // Simulate a competing worker with stale WordPress negative option cache.
        wp_cache_set( 'notoptions', array( $keys['lock'] => true ), 'options' );
        $assert( $two->acquire( $keys['active'], 30 ) === null && get_option( $keys['lock'] ) === $lease );
        $two->release( $keys['active'], 'wrong-owner' );
        $assert( $two->acquire( $keys['active'], 30 ) === null );
        $one->release( $keys['active'], $lease );
        $next = $two->acquire( $keys['active'], 30 );
        $assert( is_string( $next ) );
        $two->release( $keys['active'], $next );
    } );
    $test( 'Lock DB: expired/crashed lease reclaimed, old owner cannot delete new lease', static function () use ( $assert, $keys ) {
        $old = wp_json_encode( array( 'token' => 'expired', 'expires' => time() - 1 ) );
        add_option( $keys['lock'], $old, '', false );
        $lock = new RefreshLock();
        $new = $lock->acquire( $keys['active'], 30 );
        $assert( is_string( $new ) && $new !== $old );
        $lock->release( $keys['active'], $old );
        $assert( get_option( $keys['lock'] ) === $new );
        $lock->release( $keys['active'], $new );
    } );
    $test( 'Lock DB: malformed lease does not deadlock refresh', static function () use ( $assert, $keys ) {
        add_option( $keys['lock'], 'malformed', '', false );
        $lock = new RefreshLock();
        $lease = $lock->acquire( $keys['active'], 30 );
        $assert( is_string( $lease ) );
        $lock->release( $keys['active'], $lease );
    } );
    $test( 'Policy: filter types and hard bounds are safe', static function () use ( $assert ) {
        $policy = new ReliabilityPolicy();
        foreach ( array( 'cache', 'lkg', 'negative_cache', 'failure', 'refresh_lock' ) as $kind ) {
            $default = $policy->ttl( $kind );
            $bad = static fn() => array( 'invalid' );
            add_filter( 'vnap_' . $kind . '_ttl', $bad );
            $assert( $policy->ttl( $kind ) === $default );
            remove_filter( 'vnap_' . $kind . '_ttl', $bad );
            $negative = static fn() => -100;
            add_filter( 'vnap_' . $kind . '_ttl', $negative );
            $assert( $policy->ttl( $kind ) > 0 );
            remove_filter( 'vnap_' . $kind . '_ttl', $negative );
        }
        $huge = static fn() => 100;
        add_filter( 'vnap_provider_max_attempts', $huge );
        $assert( $policy->max_attempts() === 2 );
        remove_filter( 'vnap_provider_max_attempts', $huge );
    } );
    $test( 'Policy: max attempts filter can disable retry', static function () use ( $assert, $make, $http_error ) {
        $single = static fn() => 1;
        add_filter( 'vnap_provider_max_attempts', $single );
        try {
            list( $provider, $cache, $http ) = $make( array( $http_error( 503 ) ) );
            $provider->get_provinces();
            $assert( $http->calls === 1 );
        } finally { remove_filter( 'vnap_provider_max_attempts', $single ); }
    } );
    foreach ( array( '9999999999999999999999' => 300, '0' => 15, 'garbage' => 60 ) as $header => $expected ) {
        $test( 'Policy: Retry-After clamp ' . $header, static function () use ( $assert, $http_error, $header, $expected ) {
            $assert( ( new ReliabilityPolicy() )->failure_ttl( $http_error( 429, (string) $header ) ) === $expected );
        } );
    }
    $test( 'Policy: HTTP-date Retry-After is parsed', static function () use ( $assert, $http_error ) {
        $ttl = ( new ReliabilityPolicy() )->failure_ttl( $http_error( 429, gmdate( 'D, d M Y H:i:s \G\M\T', time() + 120 ) ) );
        $assert( $ttl >= 119 && $ttl <= 120 );
    } );
    $test( 'HTTP: 429 classification and header retained internally, v1 fields unchanged', static function () use ( $assert, &$http_mode, $response ) {
        $http_mode = $response( 429, '{}', array( 'retry-after' => '120' ) );
        $error = ( new HttpClient() )->get( Provider::UPSTREAM . '/p/' );
        $assert( $error->get_error_data()['classification'] === 'http_429' && $error->get_error_data()['retry_after'] === '120' );
        $assert( ErrorMapper::map( $error )->get_error_data() === array( 'status' => 502, 'upstream_status' => 429 ) );
    } );
    foreach ( array( 'provinces', 'wards', 'province/1' ) as $route ) {
        $test( 'REST: ' . $route . ' stale fallback has identical public response', static function () use ( $assert, $route, &$http_mode, &$http_count ) {
            $http_mode = 'valid'; $http_count = 0;
            $request = new \WP_REST_Request( 'GET', '/vn-address/v1/' . $route );
            $request->set_param( 'province_code', 1 );
            $before = rest_get_server()->dispatch( $request );
            $assert( $before->get_status() === 200 );
            foreach ( array( null, 1 ) as $code ) {
                delete_transient( Provider::runtime_keys( $code )['active'] );
            }
            $http_mode = 'failure';
            $after = rest_get_server()->dispatch( $request );
            $assert( $after->get_status() === 200 && $after->get_data() === $before->get_data() && $http_count === 2 );
        } );
    }
    $test( 'Diagnostics: failures observable, cached fallback does not log each hit', static function () use ( $assert, $make, $list, $transport, $keys, &$events ) {
        list( $provider, $cache ) = $make( array( $list, $transport ) );
        $provider->get_provinces(); $cache->delete( $keys['active'] );
        $provider->get_provinces(); $count = count( $events );
        $assert( in_array( 'provider_failure', array_column( $events, 0 ), true ) && in_array( 'lkg_fallback', array_column( $events, 0 ), true ) );
        $provider->get_provinces();
        $assert( count( $events ) === $count );
        foreach ( $events as $event ) {
            $assert( array_diff( array_keys( $event[1] ), array( 'resource', 'error_code', 'classification', 'status' ) ) === array() );
        }
    } );
} finally {
    restore_error_handler();
    remove_filter( 'pre_http_request', $intercept, PHP_INT_MAX );
    remove_filter( 'vnap_log_to_error_log', '__return_false' );
    remove_action( 'vnap_diagnostic', $diagnostic, 10 );
    $state->restore();
}
if ( $failed ) {
    \WP_CLI::error( implode( "\n", $failed ) );
}
\WP_CLI::success( $passed . ' reliability tests passed; no internet or sleeps.' );
