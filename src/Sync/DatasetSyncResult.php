<?php
namespace Tunlamweb\VNAddressPro\Sync;
defined( 'ABSPATH' ) || exit;

final class DatasetSyncResult {
    public const SUCCESS = 'SUCCESS';
    public const NO_UPDATE = 'NO_UPDATE';
    public const UPDATE_AVAILABLE = 'UPDATE_AVAILABLE';
    public const REMOTE_OLDER = 'REMOTE_DATASET_OLDER';
    public const VERSION_CONFLICT = 'VERSION_CHECKSUM_CONFLICT';
    public const LOCKED = 'SYNC_ALREADY_RUNNING';
    public const DOWNLOAD_ERROR = 'DOWNLOAD_ERROR';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const IMPORT_ERROR = 'IMPORT_ERROR';
    public const VERIFY_ERROR = 'VERIFY_ERROR';
    public const HEALTHY = 'HEALTHY';
    public const DOWNGRADE_BLOCKED = 'DOWNGRADE_BLOCKED';
    public function __construct( public readonly string $status, public readonly string $source = '', public readonly string $old_version = '', public readonly string $new_version = '' ) {}
    public function to_array(): array { return array( 'status' => $this->status, 'source' => $this->source, 'old_version' => $this->old_version, 'new_version' => $this->new_version, 'at' => time() ); }
    public function message(): string {
        if ( $this->status === self::SUCCESS && $this->source !== '' ) {
            return sprintf( __( 'Dataset published successfully. Source: %s.', 'vn-address-pro' ), $this->source );
        }
        $messages = array(
            self::SUCCESS => __( 'Dataset published successfully.', 'vn-address-pro' ),
            self::NO_UPDATE => __( 'Current. No update required.', 'vn-address-pro' ),
            self::UPDATE_AVAILABLE => __( 'Update available.', 'vn-address-pro' ),
            self::REMOTE_OLDER => __( 'Remote dataset is older; no downgrade performed.', 'vn-address-pro' ),
            self::VERSION_CONFLICT => __( 'Version/checksum conflict. Current data was preserved.', 'vn-address-pro' ),
            self::LOCKED => __( 'A dataset operation is already running.', 'vn-address-pro' ),
            self::DOWNLOAD_ERROR => __( 'The remote dataset could not be downloaded.', 'vn-address-pro' ),
            self::VALIDATION_ERROR => __( 'The candidate dataset failed validation.', 'vn-address-pro' ),
            self::IMPORT_ERROR => __( 'Import failed. Existing data was preserved.', 'vn-address-pro' ),
            self::VERIFY_ERROR => __( 'Verification failed. Existing data was preserved.', 'vn-address-pro' ),
            self::HEALTHY => __( 'The dataset is healthy; no repair is needed.', 'vn-address-pro' ),
            self::DOWNGRADE_BLOCKED => __( 'The bundle is older. Explicit downgrade confirmation is required.', 'vn-address-pro' ),
        );
        return $messages[ $this->status ] ?? __( 'The dataset operation needs attention.', 'vn-address-pro' );
    }
}
