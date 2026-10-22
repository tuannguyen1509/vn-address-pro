<?php
namespace Tunlamweb\VNAddressPro\Migration;
defined( 'ABSPATH' ) || exit;

/** Bounded, non-autoloaded journals. Rollback data only; no rollback UI in this phase. */
final class MigrationAudit {
    public const INDEX = 'vnap_migration_audits';
    public const RETAIN = 20;
    public static function key( string $id ): string { return 'vnap_migration_audit_' . $id; }
    public function begin( array $run ): bool {
        if ( ! add_option( self::key( $run['id'] ), $run, '', false ) ) { return false; }
        $index = get_option( self::INDEX, array() ); $index = is_array( $index ) ? $index : array();
        array_unshift( $index, $run['id'] );
        $retained = array_slice( array_unique( $index ), 0, self::RETAIN );
        if ( ! update_option( self::INDEX, $retained, false ) && get_option( self::INDEX ) !== $retained ) {
            // Fail before address writes; do not leave an unindexed journal behind.
            delete_option( self::key( $run['id'] ) );
            return false;
        }
        foreach ( array_slice( $index, self::RETAIN ) as $id ) { if ( is_string( $id ) && preg_match( '/\A[a-f0-9-]{36}\z/', $id ) ) { delete_option( self::key( $id ) ); } }
        return true;
    }
    public function save( array $run ): bool {
        $old = get_option( self::key( $run['id'] ), null );
        return $old === $run || update_option( self::key( $run['id'] ), $run, false );
    }
    public function read( string $id ): ?array { $run = get_option( self::key( $id ), null ); return is_array( $run ) ? $run : null; }
    public function recent(): array {
        $index = get_option( self::INDEX, array() ); $runs = array();
        foreach ( is_array( $index ) ? array_slice( $index, 0, self::RETAIN ) : array() as $id ) {
            if ( is_string( $id ) && preg_match( '/\A[a-f0-9-]{36}\z/', $id ) ) { $run = $this->read( $id ); if ( $run ) { $runs[] = $run; } }
        }
        return $runs;
    }
}
