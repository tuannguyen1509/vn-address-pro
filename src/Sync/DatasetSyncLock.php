<?php
namespace Tunlamweb\VNAddressPro\Sync;
defined( 'ABSPATH' ) || exit;

/** Separate long-lived sync lease. Conditional SQL protects ownership across expired workers. */
final class DatasetSyncLock {
    public const KEY = 'vnap_dataset_sync_lock';
    public const TTL = 600;
    public function acquire(): ?string {
        global $wpdb;
        $lease = wp_json_encode( array( 'owner' => wp_generate_uuid4(), 'expires' => time() + self::TTL ) );
        $old_errors = $wpdb->suppress_errors();
        try {
            $insert = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", self::KEY, $lease ) );
            if ( $insert === 1 ) { return $lease; }
            if ( $insert === false ) { return null; }
            $old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", self::KEY ) );
            $record = is_string( $old ) ? json_decode( $old, true ) : null;
            if ( $old === null || ( is_array( $record ) && is_int( $record['expires'] ?? null ) && $record['expires'] > time() ) ) { return null; }
            return $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", $lease, self::KEY, $old ) ) === 1 ? $lease : null;
        } finally { $wpdb->suppress_errors( $old_errors ); }
    }
    /** Called inside install transaction; prevents lease reclamation until commit/rollback. */
    public function owns( string $lease ): bool {
        global $wpdb;
        $record = json_decode( $lease, true );
        return ( $record['expires'] ?? 0 ) > time() && $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", self::KEY ) ) === $lease;
    }
    public function release( string $lease ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", self::KEY, $lease ) );
    }
    public function active(): bool {
        global $wpdb;
        $value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", self::KEY ) );
        $record = is_string( $value ) ? json_decode( $value, true ) : null;
        return is_array( $record ) && ( $record['expires'] ?? 0 ) > time();
    }
}
