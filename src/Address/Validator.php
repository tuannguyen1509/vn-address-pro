<?php
namespace Tunlamweb\VNAddressPro\Address;

defined( 'ABSPATH' ) || exit;

/** Guard consumed fields without tightening the supported v1 scalar coercions. */
final class Validator {
    public static function normalize_v1_province_code( $value ): int {
        return absint( $value );
    }

    public static function normalize_v1_search( $value ): string {
        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }

    public function provinces( array $provinces ): bool {
        if ( ! array_is_list( $provinces ) ) {
            return false;
        }
        foreach ( $provinces as $province ) {
            if ( ! is_array( $province ) || ! $this->province_fields( $province ) ) {
                return false;
            }
        }
        return true;
    }

    public function province( array $province ): bool {
        if ( ! $this->province_fields( $province ) ) {
            return false;
        }
        $wards = $province['wards'] ?? array();
        if ( ! is_array( $wards ) ) {
            return false;
        }
        foreach ( $wards as $ward ) {
            if ( ! is_array( $ward ) || ! $this->scalar_fields( $ward, array( 'code', 'name', 'division_type', 'codename', 'province_code' ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function province_fields( array $province ): bool {
        return $this->scalar_fields( $province, array( 'code', 'name', 'codename', 'phone_code' ) );
    }

    private function scalar_fields( array $record, array $fields ): bool {
        foreach ( $fields as $field ) {
            if ( isset( $record[ $field ] ) && ! is_scalar( $record[ $field ] ) ) {
                return false;
            }
        }
        return true;
    }
}
