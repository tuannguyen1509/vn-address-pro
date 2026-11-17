<?php
namespace Tunlamweb\VNAddressPro\Admin;
defined( 'ABSPATH' ) || exit;

/** Display labels only. Stored options, audit data and public API values remain unchanged. */
final class Presentation {
    public static function value( string $value ): string {
        $labels = array(
            'disabled' => 'Disabled', 'daily' => 'Daily', 'weekly' => 'Weekly', 'vnap_monthly' => 'Monthly (30 days)',
            'classic' => 'Classic Checkout', 'block' => 'Checkout Block', 'unknown' => 'Not recorded', 'absent' => 'Not installed',
            'SUCCESS' => 'Dataset published successfully.', 'NO_UPDATE' => 'Current. No update required.', 'UPDATE_AVAILABLE' => 'Update available.',
            'HEALTHY' => 'Healthy', 'SYNC_ALREADY_RUNNING' => 'A dataset operation is already running.',
            'DOWNLOAD_ERROR' => 'The remote dataset could not be downloaded.', 'VALIDATION_ERROR' => 'The candidate dataset failed validation.',
            'IMPORT_ERROR' => 'Import failed. Existing data was preserved.', 'VERIFY_ERROR' => 'Verification failed. Existing data was preserved.',
            'REMOTE_DATASET_OLDER' => 'Remote dataset is older; no downgrade performed.', 'VERSION_CHECKSUM_CONFLICT' => 'Version/checksum conflict. Current data was preserved.',
            'DOWNGRADE_BLOCKED' => 'The bundle is older. Explicit downgrade confirmation is required.',
            'HIGH' => 'High confidence', 'MEDIUM' => 'Medium confidence', 'LOW' => 'Low confidence', 'NONE' => 'Not matched',
            'MIGRATABLE_HIGH' => 'Ready for reviewed migration', 'MIGRATABLE_MEDIUM' => 'Additional review required',
            'NEEDS_REVIEW' => 'Additional review required', 'UNRESOLVED' => 'Not matched', 'AMBIGUOUS' => 'Multiple possible matches',
            'INVALID_DATA' => 'Invalid input data', 'NON_VIETNAM' => 'Outside Vietnam', 'ALREADY_CANONICAL' => 'Already standardized',
            'APPLIED' => 'Applied', 'PREPARED' => 'Prepared', 'UNCHANGED' => 'Unchanged', 'FAILED_SAVE' => 'Could not save', 'FAILED_VALIDATION' => 'Validation failed',
            'SKIPPED_STALE' => 'Skipped: data changed', 'SKIPPED_CONFIDENCE' => 'Skipped: insufficient confidence',
            'UNKNOWN_COUNTRY' => 'Country is missing; confirmation required', 'HISTORICAL_SNAPSHOT_RETAINED' => 'Historical display address retained',
            'PROVINCE_META_CONFLICT' => 'Province metadata conflicts with the address', 'BARE_PROVINCE_CODE_UNVERIFIED' => 'Legacy province code requires review',
            'ADMINISTRATIVE_PREFIX_CHANGED' => 'Administrative unit type changed', 'DISTRICT_ONLY' => 'Historical district requires review',
            'customer' => 'Customer', 'order' => 'Order', 'billing' => 'Billing', 'shipping' => 'Shipping',
        );
        return isset( $labels[ $value ] ) ? __( $labels[ $value ], 'vn-address-pro' ) : $value;
    }
}
