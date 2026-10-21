<?php
namespace Tunlamweb\VNAddressPro\Data;
use Tunlamweb\VNAddressPro\Database\TableNames;
use Tunlamweb\VNAddressPro\Database\SchemaManager;
defined( 'ABSPATH' ) || exit;

final class DatasetIntegrityAuditor {
    /** Two bounded table reads, no N+1. Call only from explicit tools/sync, never frontend. */
    public function snapshot( string $version ): array|\WP_Error {
        global $wpdb;
        $old = $wpdb->suppress_errors();
        try {
            $p = TableNames::provinces(); $w = TableNames::wards();
            $provinces = $wpdb->get_results( "SELECT code,name,division_type,codename,phone_code FROM $p ORDER BY code", ARRAY_A );
            if ( $wpdb->last_error ) { return new \WP_Error( 'tables_unreadable' ); }
            $wards = $wpdb->get_results( "SELECT code,province_code,name,division_type,codename,position FROM $w ORDER BY province_code,position,code", ARRAY_A );
            if ( $wpdb->last_error ) { return new \WP_Error( 'tables_unreadable' ); }
            $map = array();
            foreach ( $provinces as $row ) {
                $row['code'] = (int) $row['code']; $row['phone_code'] = $row['phone_code'] === null ? null : (int) $row['phone_code'];
                $row['wards'] = array(); $map[ $row['code'] ] = $row;
            }
            foreach ( $wards as $row ) {
                $row['code'] = (int) $row['code']; $row['province_code'] = (int) $row['province_code'];
                if ( ! isset( $map[ $row['province_code'] ] ) ) { return new \WP_Error( 'orphan_wards' ); }
                if ( (int) $row['position'] !== count( $map[ $row['province_code'] ]['wards'] ) ) { return new \WP_Error( 'ward_position' ); }
                unset( $row['position'] ); $map[ $row['province_code'] ]['wards'][] = $row;
            }
            return array( 'schema_version' => DatasetValidator::SCHEMA_VERSION, 'dataset_version' => $version, 'provinces' => array_values( $map ) );
        } finally { $wpdb->suppress_errors( $old ); }
    }

    public function audit(): array {
        global $wpdb;
        $m = ( new DatasetMetadata() )->read(); $version = get_option( DatasetMetadata::VERSION, '' );
        $issues = array(); $engines = array(); $old = $wpdb->suppress_errors();
        foreach ( array( TableNames::provinces(), TableNames::wards(), $wpdb->options ) as $table ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
            $engines[ $table ] = $engine ?? 'Missing';
            if ( strtoupper( (string) $engine ) !== 'INNODB' ) { $issues[] = 'transactional_tables'; }
        }
        $p = TableNames::provinces(); $w = TableNames::wards();
        $pc_actual = $wpdb->get_var( "SELECT COUNT(*) FROM $p" );
        $wc_actual = $wpdb->get_var( "SELECT COUNT(*) FROM $w" );
        $orphan_count = $wpdb->get_var( "SELECT COUNT(*) FROM $w w LEFT JOIN $p p ON p.code=w.province_code WHERE p.code IS NULL" );
        $duplicates = array();
        foreach ( array( $p, $w ) as $table ) {
            if ( $wpdb->get_var( "SELECT code FROM $table GROUP BY code HAVING COUNT(*)>1 LIMIT 1" ) !== null ) { $duplicates[] = $table; $issues[] = 'duplicate_codes'; }
        }
        $wpdb->suppress_errors( $old );
        if ( (int) get_option( 'vnap_db_version' ) !== SchemaManager::VERSION ) { $issues[] = 'schema_version'; }
        if ( ! DatasetValidator::valid_version( $version ) || ( $m['dataset_version'] ?? null ) !== $version
            || ( $m['schema_version'] ?? null ) !== DatasetValidator::SCHEMA_VERSION || ! is_string( $m['source'] ?? null ) || trim( $m['source'] ) === ''
            || ! is_int( $m['province_count'] ?? null ) || ! is_int( $m['ward_count'] ?? null ) || ! is_string( $m['generated_at'] ?? null )
            || ! is_string( $m['checksum'] ?? null ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $m['checksum'] ) ) { $issues[] = 'metadata'; }
        if ( DatasetValidator::valid_version( $version ) && ( $m['generated_at'] ?? '' ) !== \DateTimeImmutable::createFromFormat( '!YmdHis', $version, new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ) ) { $issues[] = 'metadata'; }
        $data = $this->snapshot( is_string( $version ) ? $version : '' );
        $pc = 0; $wc = 0; $checksum = null;
        if ( is_wp_error( $data ) ) { $issues[] = $data->get_error_code(); }
        else {
            $pc = count( $data['provinces'] );
            foreach ( $data['provinces'] as $p ) { $wc += count( $p['wards'] ); }
            if ( ! ( new DatasetValidator() )->validate( $data ) ) { $issues[] = 'records'; }
            if ( $pc !== ( $m['province_count'] ?? null ) || $wc !== ( $m['ward_count'] ?? null ) ) { $issues[] = 'counts'; }
            $checksum = DatasetPackage::content_checksum( $data );
            $expected = $m['content_checksum'] ?? null;
            // Phase 4 bundle predates content hashes; verify against its known-good artifact.
            if ( $expected === null && $version === BundledDataset::VERSION && ( $m['checksum'] ?? '' ) === BundledDataset::CHECKSUM ) {
                $bundle = ( new DatasetLoader() )->load();
                if ( ! is_wp_error( $bundle ) ) { $expected = DatasetPackage::content_checksum( $bundle['dataset'] ); }
            }
            if ( ! is_string( $expected ) || ! hash_equals( $expected, $checksum ) ) { $issues[] = 'content_checksum'; }
        }
        return array( 'healthy' => ! $issues, 'issues' => array_values( array_unique( $issues ) ), 'province_count' => (int) $pc_actual, 'ward_count' => (int) $wc_actual,
            'orphan_wards' => (int) $orphan_count, 'duplicate_issues' => $duplicates, 'content_checksum' => $checksum, 'engines' => $engines, 'at' => time() );
    }
}
