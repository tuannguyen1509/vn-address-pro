<?php
namespace Tunlamweb\VNAddressPro\Contracts;

defined( 'ABSPATH' ) || exit;

interface CacheInterface {
    /** False means cache miss; an empty array is a hit. */
    public function get( string $key );

    public function set( string $key, $value, int $ttl ): bool;

    public function delete( string $key ): bool;
}
