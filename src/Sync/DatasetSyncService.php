<?php
namespace Tunlamweb\VNAddressPro\Sync;
use Tunlamweb\VNAddressPro\Contracts\DatasetProviderInterface;
use Tunlamweb\VNAddressPro\Data\{DatasetInstaller,DatasetLoader,DatasetPackage,DatasetMetadata,DatasetIntegrityAuditor,DatasetValidator,BundledDataset};
use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiDatasetProvider;
use Tunlamweb\VNAddressPro\Support\Logger;
defined( 'ABSPATH' ) || exit;

final class DatasetSyncService {
    public function __construct( private ?DatasetProviderInterface $provider = null, private ?DatasetLoader $bundle_loader = null ) { $this->provider ??= new ProvinceOpenApiDatasetProvider(); }
    public function check(): DatasetSyncResult { return $this->run( 'check' ); }
    public function sync(): DatasetSyncResult { return $this->run( 'sync' ); }
    public function repair(): DatasetSyncResult { return $this->run( 'repair' ); }
    public function reinstall( bool $confirm_downgrade = false ): DatasetSyncResult { return $this->run( 'bundle', $confirm_downgrade ); }

    private function run( string $mode, bool $confirm_downgrade = false ): DatasetSyncResult {
        $lock = new DatasetSyncLock(); $lease = $lock->acquire();
        if ( $lease === null ) { return new DatasetSyncResult( DatasetSyncResult::LOCKED ); }
        $metadata = new DatasetMetadata(); $old = get_option( DatasetMetadata::VERSION, '' ); $old = is_string( $old ) ? $old : '';
        $result = new DatasetSyncResult( DatasetSyncResult::IMPORT_ERROR );
        $logger = new Logger();
        try {
            $logger->event( 'dataset_sync_start', array( 'classification' => $mode ) );
            if ( $mode === 'repair' && ( new DatasetIntegrityAuditor() )->audit()['healthy'] ) {
                $result = new DatasetSyncResult( DatasetSyncResult::HEALTHY );
                return $result;
            }
            $package = $mode === 'bundle' ? $this->bundle() : $this->provider->fetch_dataset();
            // Repair may recover from the bundle, but never silently downgrade a newer installation.
            if ( $mode === 'repair' && ( is_wp_error( $package ) || ! DatasetPackage::valid( $package ) ) ) { $package = $this->bundle(); }
            if ( is_wp_error( $package ) ) {
                $result = new DatasetSyncResult( $package->get_error_code() === 'vnap_dataset_download' ? DatasetSyncResult::DOWNLOAD_ERROR : DatasetSyncResult::VALIDATION_ERROR ); return $result;
            }
            if ( ! DatasetPackage::valid( $package ) ) { $result = new DatasetSyncResult( DatasetSyncResult::VALIDATION_ERROR ); return $result; }
            $m = $package['manifest']; $new = $m['dataset_version']; $source = $m['source'];
            $current = $metadata->read(); $comparison = DatasetValidator::valid_version( $old ) ? strcmp( $new, $old ) : 1;
            $status = DatasetSyncResult::UPDATE_AVAILABLE;
            if ( $comparison < 0 && ! ( $mode === 'bundle' && $confirm_downgrade ) ) {
                $status = $mode === 'bundle' || $mode === 'repair' ? DatasetSyncResult::DOWNGRADE_BLOCKED : DatasetSyncResult::REMOTE_OLDER;
            } elseif ( $comparison === 0 ) {
                $hash = $current['content_checksum'] ?? null;
                if ( $hash === null && $old === BundledDataset::VERSION && ( $current['checksum'] ?? '' ) === BundledDataset::CHECKSUM ) {
                    $bundle = ( new DatasetLoader() )->load();
                    if ( ! is_wp_error( $bundle ) ) { $hash = DatasetPackage::content_checksum( $bundle['dataset'] ); }
                }
                $equal = $hash !== null ? $hash === DatasetPackage::content_checksum( $package['dataset'] ) : ( $current['checksum'] ?? '' ) === $m['checksum'];
                $status = $equal ? DatasetSyncResult::NO_UPDATE : DatasetSyncResult::VERSION_CONFLICT;
                // Explicit known-good reinstall may restore missing metadata or damaged rows.
                if ( $mode === 'bundle' || ( $mode === 'repair' && $equal ) ) { $status = DatasetSyncResult::UPDATE_AVAILABLE; }
            }
            if ( $mode === 'check' || $status !== DatasetSyncResult::UPDATE_AVAILABLE ) {
                $result = new DatasetSyncResult( $status, $source, $old, $new ); return $result;
            }
            $before = ( new DatasetIntegrityAuditor() )->snapshot( $old );
            $old_codes = is_wp_error( $before ) ? array() : array_column( $before['provinces'], 'code' );
            $import = ( new DatasetInstaller() )->install_package( $package, $mode === 'repair' || $mode === 'bundle', static function () use ( $lock, $lease, $old ) {
                global $wpdb;
                $version = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", DatasetMetadata::VERSION ) );
                return $lock->owns( $lease ) && ( $version ?? '' ) === $old;
            } );
            if ( is_wp_error( $import ) ) {
                $result = new DatasetSyncResult( $import->get_error_code() === 'vnap_dataset_verify' ? DatasetSyncResult::VERIFY_ERROR : DatasetSyncResult::IMPORT_ERROR, $source, $old, $new ); return $result;
            }
            $result = new DatasetSyncResult( DatasetSyncResult::SUCCESS, $source, $old, $new );
            ( new RemoteCacheCleaner() )->clear( $old_codes );
            delete_option( DatasetMetadata::AUDIT );
            try { do_action( 'vnap_dataset_updated', $old, $new ); }
            catch ( \Throwable $observer_error ) { $logger->event( 'dataset_observer_failure', array( 'classification' => 'observer' ) ); }
            return $result;
        } catch ( \Throwable $error ) {
            // Never persist exception text, URLs, headers or credentials.
            return $result;
        } finally {
            try {
                $metadata->record( $result->to_array(), $mode === 'check' );
                $logger->event( $result->status === DatasetSyncResult::SUCCESS ? 'dataset_sync_success' : 'dataset_sync_result', array( 'classification' => $mode, 'status' => $result->status ) );
            } finally { $lock->release( $lease ); }
        }
    }

    private function bundle(): array|\WP_Error {
        $package = ( $this->bundle_loader ?? new DatasetLoader() )->load();
        if ( ! is_wp_error( $package ) && ( $package['manifest']['dataset_version'] !== BundledDataset::VERSION || $package['manifest']['checksum'] !== BundledDataset::CHECKSUM ) ) {
            return new \WP_Error( 'vnap_invalid_payload' );
        }
        return $package;
    }
}
