<?php
namespace Tunlamweb\VNAddressPro\Sync;
use Tunlamweb\VNAddressPro\Data\DatasetMetadata;
defined( 'ABSPATH' ) || exit;

final class DatasetScheduler {
    public const HOOK = 'vnap_dataset_sync';
    public function register(): void {
        add_filter( 'cron_schedules', array( $this, 'intervals' ) );
        add_action( self::HOOK, array( $this, 'run' ) );
        add_action( 'update_option_' . DatasetMetadata::SCHEDULE, array( $this, 'changed' ), 10, 2 );
        add_action( 'add_option_' . DatasetMetadata::SCHEDULE, array( $this, 'added' ), 10, 2 );
    }
    public function intervals( array $schedules ): array {
        $schedules['vnap_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => __( 'Every 30 days', 'vn-address-pro' ) );
        return $schedules;
    }
    public static function sanitize( $value ): string { return in_array( $value, array( 'disabled','daily','weekly','vnap_monthly' ), true ) ? $value : 'disabled'; }
    public function changed( $old, $new ): void { $this->reconcile(); }
    public function added( $key, $value ): void { $this->reconcile(); }
    public function reconcile(): void {
        $schedule = self::sanitize( get_option( DatasetMetadata::SCHEDULE, 'disabled' ) );
        $event = wp_get_scheduled_event( self::HOOK );
        if ( $schedule === 'disabled' ) { self::clear(); return; }
        if ( $event && $event->schedule === $schedule ) { return; }
        self::clear();
        wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, self::HOOK );
    }
    public static function clear(): void { wp_clear_scheduled_hook( self::HOOK ); }
    public function run(): void {
        if ( self::sanitize( get_option( DatasetMetadata::SCHEDULE, 'disabled' ) ) === 'disabled' ) { return; }
        // Fetch/compare/import once. Unchanged content never rewrites live rows.
        ( new DatasetSyncService() )->sync();
    }
}
