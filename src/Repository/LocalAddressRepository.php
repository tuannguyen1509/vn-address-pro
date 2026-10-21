<?php
namespace Tunlamweb\VNAddressPro\Repository;

use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;
use Tunlamweb\VNAddressPro\Database\TableNames;
use Tunlamweb\VNAddressPro\Database\SchemaManager;
use Tunlamweb\VNAddressPro\Data\DatasetValidator;

defined( 'ABSPATH' ) || exit;

final class LocalAddressRepository implements AddressRepositoryInterface {
    public function has_usable_dataset(): bool {
        $metadata = get_option( 'vnap_dataset_metadata', array() );
        if ( (int) get_option( 'vnap_db_version', 0 ) !== SchemaManager::VERSION || ! is_array( $metadata )
            || empty( $metadata['dataset_version'] ) || ( $metadata['schema_version'] ?? null ) !== DatasetValidator::SCHEMA_VERSION
            || get_option( 'vnap_dataset_version' ) !== $metadata['dataset_version'] ) {
            return false;
        }
        $p = TableNames::provinces(); $w = TableNames::wards();
        $rows = $this->read( "SELECT (SELECT code FROM $p LIMIT 1) AS province_code, (SELECT code FROM $w LIMIT 1) AS ward_code" );
        return ! is_wp_error( $rows ) && ! empty( $rows[0]['province_code'] )
            && ( ( $metadata['ward_count'] ?? 0 ) === 0 || ! empty( $rows[0]['ward_code'] ) );
    }

    public function get_provinces(): array|\WP_Error {
        $table = TableNames::provinces();
        $rows = $this->read( "SELECT code,name,division_type,codename,phone_code FROM $table ORDER BY code" );
        if ( is_wp_error( $rows ) ) {
            return $rows;
        }
        if ( ! $rows ) {
            return $this->unavailable();
        }
        return array_map( array( $this, 'province_record' ), $rows );
    }

    public function get_province( int $province_code ): array|\WP_Error {
        global $wpdb;
        $p = TableNames::provinces(); $w = TableNames::wards();
        // A single statement sees one committed snapshot across both tables.
        $rows = $this->read( $wpdb->prepare(
            "SELECT p.code,p.name,p.division_type,p.codename,p.phone_code,
                w.code AS ward_code,w.province_code,w.name AS ward_name,w.division_type AS ward_type,w.codename AS ward_codename
                FROM $p p LEFT JOIN $w w ON w.province_code=p.code WHERE p.code=%d ORDER BY w.position", $province_code
        ) );
        if ( is_wp_error( $rows ) ) {
            return $rows;
        }
        if ( ! $rows ) {
            return $this->not_found();
        }
        $province = $this->province_record( $rows[0] );
        $province['wards'] = array();
        foreach ( $rows as $row ) {
            if ( $row['ward_code'] !== null ) {
                $province['wards'][] = array( 'code' => (int) $row['ward_code'], 'province_code' => (int) $row['province_code'], 'name' => $row['ward_name'], 'division_type' => $row['ward_type'], 'codename' => $row['ward_codename'] );
            }
        }
        return $province;
    }

    public function get_wards( int $province_code ): array|\WP_Error {
        return $this->get_province( $province_code );
    }

    public function get_ward( int $ward_code ): array|\WP_Error {
        global $wpdb;
        $table = TableNames::wards();
        $rows = $this->read( $wpdb->prepare( "SELECT code,province_code,name,division_type,codename FROM $table WHERE code=%d", $ward_code ) );
        if ( is_wp_error( $rows ) ) {
            return $rows;
        }
        if ( ! $rows ) {
            return $this->not_found();
        }
        $row = $rows[0];
        $row['code'] = (int) $row['code']; $row['province_code'] = (int) $row['province_code'];
        return $row;
    }

    public function province_record( array $row ): array {
        return array( 'code' => (int) $row['code'], 'name' => $row['name'], 'division_type' => $row['division_type'], 'codename' => $row['codename'], 'phone_code' => $row['phone_code'] === null ? null : (int) $row['phone_code'] );
    }

    private function read( string $sql ): array|\WP_Error {
        global $wpdb;
        $old = $wpdb->suppress_errors();
        try {
            $rows = $wpdb->get_results( $sql, ARRAY_A );
            return $wpdb->last_error ? $this->unavailable() : ( $rows ?? array() );
        } finally {
            $wpdb->suppress_errors( $old );
        }
    }

    private function unavailable(): \WP_Error {
        return new \WP_Error( 'vnap_dataset_unavailable', __( 'The local address dataset is unavailable.', 'vn-address-pro' ) );
    }

    private function not_found(): \WP_Error {
        return new \WP_Error( 'vnap_not_found', __( 'The requested address record was not found.', 'vn-address-pro' ) );
    }
}
