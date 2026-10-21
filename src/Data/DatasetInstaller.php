<?php
namespace Tunlamweb\VNAddressPro\Data;

use Tunlamweb\VNAddressPro\Database\SchemaManager;
use Tunlamweb\VNAddressPro\Database\TableNames;
use Tunlamweb\VNAddressPro\Cache\RefreshLock;
use Tunlamweb\VNAddressPro\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class DatasetInstaller {
    public const BATCH_SIZE = 100;
    private const OPTIONS = array( 'vnap_dataset_version', 'vnap_dataset_metadata' );

    public function ensure( bool $activation = false ): bool|\WP_Error {
        $version = get_option( 'vnap_dataset_version', '' );
        $metadata = get_option( 'vnap_dataset_metadata', array() );
        if ( (int) get_option( 'vnap_db_version', 0 ) === SchemaManager::VERSION && DatasetValidator::valid_version( $version )
            && strcmp( $version, BundledDataset::VERSION ) >= 0 && is_array( $metadata )
            && ( $metadata['dataset_version'] ?? null ) === $version && ( $metadata['schema_version'] ?? null ) === DatasetValidator::SCHEMA_VERSION ) {
            return true;
        }
        if ( ! $activation && get_transient( 'vnap_dataset_install_failure' ) !== false ) {
            return $this->error();
        }
        $result = $this->install();
        if ( is_wp_error( $result ) ) {
            set_transient( 'vnap_dataset_install_failure', true, MINUTE_IN_SECONDS );
            ( new Logger() )->event( 'dataset_install_failure', array( 'classification' => 'local_install' ) );
        }
        return $result;
    }

    /** Explicit internal install; replace=true is for controlled repair/testing, never automatic downgrade. */
    public function install( ?DatasetLoader $loader = null, bool $replace = false ): bool|\WP_Error {
        global $wpdb;
        $package = ( $loader ?? new DatasetLoader() )->load();
        if ( is_wp_error( $package ) ) {
            return $package;
        }
        if ( $loader === null && ( $package['manifest']['dataset_version'] !== BundledDataset::VERSION || $package['manifest']['checksum'] !== BundledDataset::CHECKSUM ) ) {
            return $this->error();
        }
        return $this->install_package( $package, $replace );
    }

    /** Shared bundled/remote transaction pipeline. Guard runs under the transaction before any row write. */
    public function install_package( array $package, bool $replace = false, ?callable $guard = null ): bool|\WP_Error {
        global $wpdb;
        if ( ! DatasetPackage::valid( $package ) ) {
            return new \WP_Error( 'vnap_invalid_payload', __( 'The address candidate failed validation.', 'vn-address-pro' ) );
        }
        $lock = new RefreshLock();
        $lease = $lock->acquire( 'vnap_dataset_install', 60 );
        if ( $lease === null ) {
            return $this->error();
        }
        $old = $wpdb->suppress_errors();
        $transaction = false;
        try {
            $schema = ( new SchemaManager() )->ensure( true );
            if ( is_wp_error( $schema ) ) {
                return $schema;
            }
            $data = $package['dataset']; $manifest = $package['manifest'];
            $p = TableNames::provinces(); $w = TableNames::wards();
            $this->execute( 'START TRANSACTION' ); $transaction = true;
            $owner = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", RefreshLock::key( 'vnap_dataset_install' ) ) );
            if ( $owner !== $lease ) { throw new \RuntimeException( 'dataset_lease_lost' ); }
            if ( $guard !== null && ! $guard() ) { throw new \RuntimeException( 'dataset_sync_lease_lost' ); }
            // Read authoritative metadata after obtaining the installation lease/row lock.
            $installed = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'vnap_dataset_version' ) );
            if ( ! $replace && DatasetValidator::valid_version( $installed ) && strcmp( $installed, $data['dataset_version'] ) >= 0 ) {
                $this->execute( 'COMMIT' ); $transaction = false;
                return true;
            }
            $this->execute( "DELETE FROM $w" ); $this->execute( "DELETE FROM $p" );
            $province_rows = array(); $ward_rows = array();
            foreach ( $data['provinces'] as $province ) {
                $province_rows[] = array( $province['code'], $province['name'], $province['division_type'] ?? '', $province['codename'] ?? '', $province['phone_code'] ?? null );
                foreach ( $province['wards'] as $position => $ward ) {
                    $ward_rows[] = array( $ward['code'], $ward['province_code'], $ward['name'], $ward['division_type'] ?? '', $ward['codename'] ?? '', $position );
                }
            }
            $this->insert( $p, array( 'code', 'name', 'division_type', 'codename', 'phone_code' ), $province_rows );
            $this->insert( $w, array( 'code', 'province_code', 'name', 'division_type', 'codename', 'position' ), $ward_rows );
            $counts = $wpdb->get_row( "SELECT (SELECT COUNT(*) FROM $p) AS p, (SELECT COUNT(*) FROM $w) AS w", ARRAY_A );
            $orphans = $wpdb->get_var( "SELECT COUNT(*) FROM $w w LEFT JOIN $p p ON p.code=w.province_code WHERE p.code IS NULL" );
            if ( $wpdb->last_error || (int) ( $counts['p'] ?? -1 ) !== $manifest['province_count'] || (int) ( $counts['w'] ?? -1 ) !== $manifest['ward_count'] || (int) $orphans !== 0 ) {
                throw new \RuntimeException( 'dataset_verification' );
            }
            $snapshot = ( new DatasetIntegrityAuditor() )->snapshot( $data['dataset_version'] );
            if ( is_wp_error( $snapshot ) || ! ( new DatasetValidator() )->validate( $snapshot )
                || DatasetPackage::content_checksum( $snapshot ) !== DatasetPackage::content_checksum( $data ) ) {
                throw new \RuntimeException( 'dataset_verification' );
            }
            $manifest['content_checksum'] = DatasetPackage::content_checksum( $data );
            $manifest['installed_at'] = time();
            // Metadata and rows commit together. Do not publish uncommitted values in option cache.
            foreach ( array( 'vnap_dataset_version' => $data['dataset_version'], 'vnap_dataset_metadata' => $manifest ) as $key => $value ) {
                $this->execute( $wpdb->prepare(
                    "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload='no'",
                    $key, maybe_serialize( $value )
                ) );
            }
            $this->execute( 'COMMIT' ); $transaction = false;
            $this->invalidate_metadata();
            delete_transient( 'vnap_dataset_install_failure' );
            // Observers run after commit; an observer failure cannot undo a successful publish.
            try { do_action( 'vnap_dataset_installed', $manifest ); }
            catch ( \Throwable $observer_error ) { ( new Logger() )->event( 'dataset_observer_failure', array( 'classification' => 'observer' ) ); }
            return true;
        } catch ( \Throwable $error ) {
            if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
            $this->invalidate_metadata();
            return $error->getMessage() === 'dataset_verification'
                ? new \WP_Error( 'vnap_dataset_verify', __( 'The staged dataset failed verification. Existing data has been preserved.', 'vn-address-pro' ) ) : $this->error();
        } finally {
            $wpdb->suppress_errors( $old );
            $lock->release( 'vnap_dataset_install', $lease );
        }
    }

    private function insert( string $table, array $columns, array $rows ): void {
        global $wpdb;
        foreach ( array_chunk( $rows, self::BATCH_SIZE ) as $batch ) {
            $values = array(); $args = array();
            foreach ( $batch as $row ) {
                $formats = array();
                foreach ( $row as $value ) {
                    $formats[] = $value === null ? 'NULL' : ( is_int( $value ) ? '%d' : '%s' );
                    if ( $value !== null ) { $args[] = $value; }
                }
                $values[] = '(' . implode( ',', $formats ) . ')';
            }
            $sql = 'INSERT INTO ' . $table . ' (' . implode( ',', $columns ) . ') VALUES ' . implode( ',', $values );
            $this->execute( $wpdb->prepare( $sql, $args ) );
        }
    }

    private function execute( string $sql ): void {
        global $wpdb;
        if ( $wpdb->query( $sql ) === false ) { throw new \RuntimeException( 'dataset_write' ); }
    }

    private function invalidate_metadata(): void {
        foreach ( self::OPTIONS as $key ) { wp_cache_delete( $key, 'options' ); }
        wp_cache_delete( 'notoptions', 'options' );
        wp_cache_delete( 'alloptions', 'options' );
    }

    private function error(): \WP_Error {
        return new \WP_Error( 'vnap_dataset_unavailable', __( 'The local address dataset could not be installed. Existing data has been preserved.', 'vn-address-pro' ) );
    }
}
