<?php
namespace Tunlamweb\VNAddressPro\Providers\Validation;

use Tunlamweb\VNAddressPro\Address\Validator;

defined( 'ABSPATH' ) || exit;

/** Required remote fields, separate from the permissive v1 output/default mapper. */
final class ProvinceOpenApiValidator {
    public function validate( $data, ?int $requested_code = null ): bool {
        if ( ! is_array( $data ) ) {
            return false;
        }
        if ( $requested_code === null ) {
            if ( ! $data || ! array_is_list( $data ) || ! ( new Validator() )->provinces( $data ) ) {
                return false;
            }
            return $this->records( $data );
        }
        if ( ! $this->record( $data ) || (int) $data['code'] !== $requested_code || ! isset( $data['wards'] ) || ! is_array( $data['wards'] ) ) {
            return false;
        }
        if ( ! array_is_list( $data['wards'] ) || ! ( new Validator() )->province( $data ) || ! $this->records( $data['wards'] ) ) {
            return false;
        }
        foreach ( $data['wards'] as $ward ) {
            // An absent optional parent still maps to the original default 0.
            if ( isset( $ward['province_code'] ) && ( ! $this->code( $ward['province_code'] ) || (int) $ward['province_code'] !== $requested_code ) ) {
                return false;
            }
        }
        return true;
    }

    private function records( array $records ): bool {
        $seen = array();
        foreach ( $records as $record ) {
            if ( ! is_array( $record ) || ! $this->record( $record ) || isset( $seen[ (int) $record['code'] ] ) ) {
                return false;
            }
            $seen[ (int) $record['code'] ] = true;
        }
        return true;
    }

    private function record( array $record ): bool {
        return isset( $record['code'], $record['name'] ) && $this->code( $record['code'] )
            && is_string( $record['name'] ) && trim( $record['name'] ) !== '';
    }

    private function code( $value ): bool {
        if ( is_int( $value ) ) {
            return $value > 0;
        }
        if ( is_float( $value ) ) {
            return is_finite( $value ) && (int) $value > 0 && (float) (int) $value === $value;
        }
        return is_string( $value ) && ctype_digit( $value ) && (int) $value > 0
            && ltrim( $value, '0' ) === (string) (int) $value;
    }
}
