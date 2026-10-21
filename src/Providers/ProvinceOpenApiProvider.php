<?php
namespace Tunlamweb\VNAddressPro\Providers;

use Tunlamweb\VNAddressPro\Contracts\AddressProviderInterface;
use Tunlamweb\VNAddressPro\Contracts\CacheInterface;
use Tunlamweb\VNAddressPro\Http\HttpClient;
use Tunlamweb\VNAddressPro\Cache\RefreshLock;
use Tunlamweb\VNAddressPro\Providers\Validation\ProvinceOpenApiValidator;
use Tunlamweb\VNAddressPro\Support\Logger;

defined( 'ABSPATH' ) || exit;

/** Owns validated raw caching and bounded synchronous recovery. */
final class ProvinceOpenApiProvider implements AddressProviderInterface {
    public const UPSTREAM = 'https://provinces.open-api.vn/api/v2';
    public const CACHE_TTL = DAY_IN_SECONDS;

    private ProvinceOpenApiValidator $validator;
    private ReliabilityPolicy $policy;
    private RefreshLock $lock;
    private Logger $logger;
    private \Closure $clock;

    public function __construct(
        private HttpClient $http,
        private CacheInterface $cache,
        ?ProvinceOpenApiValidator $validator = null,
        ?ReliabilityPolicy $policy = null,
        ?RefreshLock $lock = null,
        ?Logger $logger = null,
        ?callable $clock = null
    ) {
        $this->validator = $validator ?? new ProvinceOpenApiValidator();
        $this->policy = $policy ?? new ReliabilityPolicy();
        $this->lock = $lock ?? new RefreshLock();
        $this->logger = $logger ?? new Logger();
        $this->clock = $clock === null ? static fn() => microtime( true ) : \Closure::fromCallable( $clock );
    }

    /** Shared naming for infrastructure consumers; active keys remain unchanged. */
    public static function runtime_keys( ?int $code = null ): array {
        $suffix = $code === null ? 'provinces_v2' : 'province_' . $code . '_depth2';
        $active = 'vn_address_' . $suffix;
        return array(
            'active' => $active, 'lkg' => 'vnap_lkg_' . $suffix,
            'negative' => 'vnap_negative_' . $suffix, 'failure' => 'vnap_provider_failure_' . $suffix,
            'lock' => RefreshLock::key( $active ),
        );
    }

    public function get_provinces(): array|\WP_Error {
        return $this->fetch( self::UPSTREAM . '/p/' );
    }

    public function get_province( int $province_code ): array|\WP_Error {
        return $this->fetch(
            self::UPSTREAM . '/p/' . $province_code . '?depth=2',
            $province_code
        );
    }

    public function get_wards( int $province_code ): array|\WP_Error {
        return $this->get_province( $province_code );
    }

    private function fetch( string $url, ?int $code = null ): array|\WP_Error {
        $keys = self::runtime_keys( $code );
        $active = $this->read_valid( $keys['active'], $code );
        if ( $active !== false ) {
            return $active;
        }
        $lkg = $this->read_valid( $keys['lkg'], $code );
        $error = $this->cached_error( $keys );
        if ( $error !== null ) {
            return $lkg !== false ? $lkg : $error;
        }
        $lease = $this->lock->acquire( $keys['active'], $this->policy->ttl( 'refresh_lock' ) );
        if ( $lease === null ) {
            $active = $this->read_valid( $keys['active'], $code );
            return $active !== false ? $active : ( $lkg !== false ? $lkg : new \WP_Error( 'vnap_cache_lock', __( 'Address data is being refreshed. Please try again shortly.', 'vn-address-pro' ) ) );
        }
        try {
            // A competing worker may have completed between the initial read and acquisition.
            $active = $this->read_valid( $keys['active'], $code );
            if ( $active !== false ) {
                return $active;
            }
            $error = $this->cached_error( $keys );
            if ( $error !== null ) {
                return $lkg !== false ? $lkg : $error;
            }
            $start = ( $this->clock )();
            for ( $attempt = 1; $attempt <= $this->policy->max_attempts(); ++$attempt ) {
                $data = $this->http->get( $url );
                if ( ! is_wp_error( $data ) ) {
                    if ( $this->validator->validate( $data, $code ) ) {
                        $this->write( $keys['active'], $data, $this->policy->ttl( 'cache' ) );
                        $this->write( $keys['lkg'], $data, $this->policy->ttl( 'lkg' ) );
                        $this->cache->delete( $keys['negative'] );
                        $this->cache->delete( $keys['failure'] );
                        return $data;
                    }
                    $missing = $code !== null && empty( $data['code'] );
                    $classification = $code !== null && isset( $data['code'] ) && is_scalar( $data['code'] ) && (int) $data['code'] !== $code ? 'province_mismatch' : 'invalid_schema';
                    $data = new \WP_Error( $missing ? 'vnap_not_found' : 'vnap_invalid_payload', __( 'The provider address structure is invalid.', 'vn-address-pro' ), array( 'classification' => $classification ) );
                }
                if ( $lkg !== false || $attempt >= $this->policy->max_attempts() || ! $this->policy->should_retry( $data, ( $this->clock )() - $start ) ) {
                    break;
                }
            }
            $error_data = $data->get_error_data();
            $status = is_array( $error_data ) ? ( $error_data['upstream_status'] ?? 0 ) : 0;
            $marker = array( 'code' => $data->get_error_code(), 'message' => $data->get_error_message(), 'data' => $error_data );
            if ( $code !== null && $status === 404 ) {
                $this->write( $keys['negative'], $marker, $this->policy->ttl( 'negative_cache' ) );
            } else {
                $this->write( $keys['failure'], $marker, $this->policy->failure_ttl( $data ) );
            }
            $this->logger->event( 'provider_failure', array(
                'resource' => $keys['active'], 'error_code' => $data->get_error_code(),
                'classification' => is_array( $error_data ) ? ( $error_data['classification'] ?? 'provider_error' ) : 'provider_error', 'status' => $status,
            ) );
            if ( $lkg !== false ) {
                $this->logger->event( 'lkg_fallback', array( 'resource' => $keys['active'] ) );
                return $lkg;
            }
            return $data;
        } finally {
            $this->lock->release( $keys['active'], $lease );
        }
    }

    private function read_valid( string $key, ?int $code ) {
        $data = $this->cache->get( $key );
        if ( $data === false || $this->validator->validate( $data, $code ) ) {
            return $data;
        }
        $this->cache->delete( $key );
        $this->logger->event( 'invalid_cached_payload', array( 'resource' => $key ) );
        return false;
    }

    private function cached_error( array $keys ): ?\WP_Error {
        foreach ( array( 'negative', 'failure' ) as $kind ) {
            $marker = $this->cache->get( $keys[ $kind ] );
            if ( is_array( $marker ) && isset( $marker['code'], $marker['message'] ) && is_string( $marker['code'] ) && is_string( $marker['message'] )
                && in_array( $marker['code'], array( 'vnap_transport_error', 'vnap_remote_http_error', 'vnap_invalid_payload', 'vnap_not_found' ), true ) ) {
                return new \WP_Error( $marker['code'], $marker['message'], $marker['data'] ?? null );
            }
        }
        return null;
    }

    private function write( string $key, array $value, int $ttl ): void {
        if ( ! $this->cache->set( $key, $value, $ttl ) && $this->cache->get( $key ) !== $value ) {
            $this->logger->event( 'cache_write_failure', array( 'resource' => $key ) );
        }
    }
}
