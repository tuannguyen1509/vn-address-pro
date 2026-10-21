<?php
namespace Tunlamweb\VNAddressPro\Data;

use Tunlamweb\VNAddressPro\Providers\Validation\ProvinceOpenApiValidator;

defined( 'ABSPATH' ) || exit;

final class DatasetValidator {
    public const SCHEMA_VERSION = 1;

    public static function valid_version( $version ): bool {
        if ( ! is_string( $version ) || ! preg_match( '/\A[0-9]{14}\z/', $version ) ) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat( '!YmdHis', $version, new \DateTimeZone( 'UTC' ) );
        return $date !== false && $date->format( 'YmdHis' ) === $version;
    }

    public function validate( $dataset ): bool {
        if ( ! is_array( $dataset ) || ( $dataset['schema_version'] ?? null ) !== self::SCHEMA_VERSION
            || ! self::valid_version( $dataset['dataset_version'] ?? null ) || empty( $dataset['provinces'] )
            || ! is_array( $dataset['provinces'] ) || ! array_is_list( $dataset['provinces'] ) ) {
            return false;
        }
        $source = new ProvinceOpenApiValidator();
        if ( ! $source->validate( $dataset['provinces'] ) ) {
            return false;
        }
        $wards = array();
        foreach ( $dataset['provinces'] as $province ) {
            if ( ! is_int( $province['code'] ) || ! $source->validate( $province, $province['code'] ) || ! $this->strings( $province )
                || ( isset( $province['phone_code'] ) && ( ! is_int( $province['phone_code'] ) || $province['phone_code'] < 0 || $province['phone_code'] > 2147483647 ) ) ) {
                return false;
            }
            foreach ( $province['wards'] as $ward ) {
                if ( ! is_int( $ward['code'] ) || ! isset( $ward['province_code'] ) || $ward['province_code'] !== $province['code']
                    || isset( $wards[ $ward['code'] ] ) || ! $this->strings( $ward ) ) {
                    return false;
                }
                $wards[ $ward['code'] ] = true;
            }
        }
        return true;
    }

    private function strings( array $record ): bool {
        foreach ( array( 'name', 'codename', 'division_type' ) as $field ) {
            if ( isset( $record[ $field ] ) ) {
                if ( ! is_string( $record[ $field ] ) ) { return false; }
                $length = preg_match_all( '/./us', $record[ $field ] );
                if ( $length === false || $length > 191 ) { return false; }
            }
        }
        return true;
    }
}
