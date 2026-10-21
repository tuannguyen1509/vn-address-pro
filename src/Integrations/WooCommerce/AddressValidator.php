<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
use Tunlamweb\VNAddressPro\Plugin;
defined( 'ABSPATH' ) || exit;

final class AddressValidator {
    public static function field( string $prefix ): string { return 'vnap_' . $prefix . '_ward_code'; }
    public static function meta( string $prefix, string $part ): string { return '_vnap_' . $prefix . '_' . $part; }
    public function resolve( array $data, string $prefix ): array|\WP_Error|null {
        if ( ( $data[ $prefix . '_country' ] ?? '' ) !== 'VN' ) { return null; }
        $state = $data[ $prefix . '_state' ] ?? '';
        $code = is_string( $state ) ? States::from_wc_state_code( $state ) : null;
        if ( $code === null ) { return new \WP_Error( 'vnap_wc_province', __( 'Vui lòng chọn Tỉnh / Thành phố hợp lệ.', 'vn-address-pro' ) ); }
        return Plugin::instance()->address_service()->validate_selection( $code, $data[ self::field( $prefix ) ] ?? '' );
    }
}
