<?php
namespace Tunlamweb\VNAddressPro\Data;
defined( 'ABSPATH' ) || exit;

final class DatasetMetadata {
    public const VERSION = 'vnap_dataset_version';
    public const DATA = 'vnap_dataset_metadata';
    public const SYNC = 'vnap_dataset_sync_state';
    public const AUDIT = 'vnap_dataset_audit';
    public const SCHEDULE = 'vnap_dataset_schedule';
    public function read(): array { $m = get_option( self::DATA, array() ); return is_array( $m ) ? $m : array(); }
    public function state(): array { $m = get_option( self::SYNC, array() ); return is_array( $m ) ? $m : array(); }
    public function record( array $result, bool $check = false ): void {
        $state = $this->state();
        $state[ $check ? 'last_check' : 'last_attempt' ] = $result;
        if ( ! $check && ( $result['status'] ?? '' ) === 'SUCCESS' ) { $state['last_success'] = $result; }
        if ( ! in_array( $result['status'] ?? '', array( 'SUCCESS', 'NO_UPDATE', 'UPDATE_AVAILABLE', 'HEALTHY' ), true ) ) { $state['last_failure'] = $result; }
        update_option( self::SYNC, $state, false );
    }
}
