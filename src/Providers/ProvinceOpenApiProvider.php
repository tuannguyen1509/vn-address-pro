<?php
namespace Tunlamweb\VNAddressPro\Providers;

use Tunlamweb\VNAddressPro\Contracts\AddressProviderInterface;
use Tunlamweb\VNAddressPro\Contracts\CacheInterface;
use Tunlamweb\VNAddressPro\Http\HttpClient;

defined( 'ABSPATH' ) || exit;

/** Sole owner of raw provider cache keys, TTL and cache-miss fetching. */
final class ProvinceOpenApiProvider implements AddressProviderInterface {
    public const UPSTREAM = 'https://provinces.open-api.vn/api/v2';
    public const CACHE_TTL = DAY_IN_SECONDS;

    public function __construct( private HttpClient $http, private CacheInterface $cache ) {}

    public function get_provinces(): array|\WP_Error {
        return $this->fetch( self::UPSTREAM . '/p/', 'vn_address_provinces_v2' );
    }

    public function get_province( int $province_code ): array|\WP_Error {
        return $this->fetch(
            self::UPSTREAM . '/p/' . $province_code . '?depth=2',
            'vn_address_province_' . $province_code . '_depth2'
        );
    }

    public function get_wards( int $province_code ): array|\WP_Error {
        return $this->get_province( $province_code );
    }

    private function fetch( string $url, string $key ): array|\WP_Error {
        $cached = $this->cache->get( $key );
        if ( $cached !== false ) {
            // Existing raw-array transients remain usable without a cold-cache upgrade.
            if ( is_array( $cached ) ) {
                return $cached;
            }
            return new \WP_Error( 'vnap_invalid_payload', __( 'The cached address payload is invalid.', 'vn-address-pro' ) );
        }
        $data = $this->http->get( $url );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        $this->cache->set( $key, $data, self::CACHE_TTL );
        return $data;
    }
}
