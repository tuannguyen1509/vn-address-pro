<?php
namespace Tunlamweb\VNAddressPro\Cache;

defined( 'ABSPATH' ) || exit;

/** Atomic leases in the existing options table, independent of object-cache backends. */
class RefreshLock {
    public static function key( string $resource ): string {
        return 'vnap_refresh_lock_' . md5( $resource );
    }

    /** The JSON lease is an ownership handle, not a public token. */
    public function acquire( string $resource, int $ttl ): ?string {
        global $wpdb;
        $key = self::key( $resource );
        $lease = wp_json_encode( array( 'token' => wp_generate_uuid4(), 'expires' => time() + max( 30, min( 60, $ttl ) ) ) );
        // add_option() can upsert after a stale negative-cache read; never use it as a mutex.
        $inserted = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $key, $lease
        ) );
        $this->invalidate( $key );
        if ( $inserted === 1 ) {
            return $lease;
        }
        if ( $inserted === false ) {
            return null;
        }
        // Read the authoritative row, not an object-cache copy, before reclaiming.
        $old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
        $record = is_string( $old ) ? json_decode( $old, true ) : null;
        if ( $old === null || ( is_array( $record ) && isset( $record['expires'] ) && is_int( $record['expires'] ) && $record['expires'] > time() ) ) {
            return null;
        }
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            $lease, $key, $old
        ) );
        $this->invalidate( $key );
        return $updated === 1 ? $lease : null;
    }

    public function release( string $resource, string $lease ): void {
        global $wpdb;
        $key = self::key( $resource );
        // An expired worker must never release a newer worker's lease.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $lease
        ) );
        $this->invalidate( $key );
    }

    private function invalidate( string $key ): void {
        wp_cache_delete( $key, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }
}
