<?php
namespace Tunlamweb\VNAddressPro\Tests\Support;

use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider;

defined( 'ABSPATH' ) || exit;

/** Test-only isolation for explicit resources, not arbitrary caches. */
final class CacheState {
    private array $transients = array();
    private array $locks = array();

    public function __construct( array $codes = array( null, 1 ) ) {
        foreach ( $codes as $code ) {
            foreach ( ProvinceOpenApiProvider::runtime_keys( $code ) as $kind => $key ) {
                if ( $kind === 'lock' ) {
                    $this->locks[ $key ] = get_option( $key, false );
                } else {
                    $ttl = (int) get_option( '_transient_timeout_' . $key, time() + DAY_IN_SECONDS ) - time();
                    $this->transients[ $key ] = array( get_transient( $key ), max( 1, $ttl ) );
                }
            }
        }
    }

    public function clear(): void {
        foreach ( $this->transients as $key => $saved ) {
            delete_transient( $key );
        }
        foreach ( $this->locks as $key => $saved ) {
            delete_option( $key );
        }
    }

    public function restore(): void {
        $this->clear();
        foreach ( $this->transients as $key => list( $value, $ttl ) ) {
            if ( $value !== false ) {
                set_transient( $key, $value, $ttl );
            }
        }
        foreach ( $this->locks as $key => $value ) {
            if ( $value !== false ) {
                add_option( $key, $value, '', false );
            }
        }
    }
}
