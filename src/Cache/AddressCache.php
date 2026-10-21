<?php
namespace Tunlamweb\VNAddressPro\Cache;

use Tunlamweb\VNAddressPro\Contracts\CacheInterface;

defined( 'ABSPATH' ) || exit;

final class AddressCache implements CacheInterface {
    public function get( string $key ) {
        return get_transient( $key );
    }

    public function set( string $key, $value, int $ttl ): bool {
        return set_transient( $key, $value, $ttl );
    }

    public function delete( string $key ): bool {
        return delete_transient( $key );
    }
}
