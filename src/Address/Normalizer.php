<?php
namespace Tunlamweb\VNAddressPro\Address;

defined( 'ABSPATH' ) || exit;

/** Legacy mapping intentionally preserved, including city classification/defaults. */
final class Normalizer {
    private const CENTRAL_CITIES = array( 1, 31, 46, 48, 79, 92 );

    public function strip_prefix( string $name ): string {
        $name = preg_replace( '/^(Thành phố|Tỉnh)\s+/u', '', trim( $name ) );
        return trim( (string) $name );
    }

    public function normalize_province( array $province ): array {
        $code = isset( $province['code'] ) ? (int) $province['code'] : 0;
        $name = $this->strip_prefix( (string) ( $province['name'] ?? '' ) );
        $is_city = in_array( $code, self::CENTRAL_CITIES, true );

        return array(
            'code'          => $code,
            'name'          => ( $is_city ? 'Thành phố ' : 'Tỉnh ' ) . $name,
            'short_name'    => $name,
            'division_type' => $is_city ? 'thành phố trung ương' : 'tỉnh',
            'codename'      => (string) ( $province['codename'] ?? '' ),
            'phone_code'    => isset( $province['phone_code'] ) ? (int) $province['phone_code'] : null,
        );
    }

    public function normalize_ward( array $ward ): array {
        return array(
            'code'          => isset( $ward['code'] ) ? (int) $ward['code'] : 0,
            'name'          => (string) ( $ward['name'] ?? '' ),
            'division_type' => (string) ( $ward['division_type'] ?? '' ),
            'codename'      => (string) ( $ward['codename'] ?? '' ),
            'province_code' => isset( $ward['province_code'] ) ? (int) $ward['province_code'] : 0,
        );
    }
}
