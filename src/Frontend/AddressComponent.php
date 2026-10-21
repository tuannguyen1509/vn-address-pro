<?php
namespace Tunlamweb\VNAddressPro\Frontend;
use Tunlamweb\VNAddressPro\Plugin;
defined( 'ABSPATH' ) || exit;

final class AddressComponent {
    public static function render( array $args = array() ): string {
        $filtered = apply_filters( 'vnap_frontend_component_config', $args );
        $config = self::config( is_array( $filtered ) ? $filtered : $args );
        Assets::enqueue();
        return ( new FieldRenderer() )->render( $config, Plugin::instance()->address_service()->get_provinces() );
    }

    private static function config( array $args ): array {
        $defaults = array(
            'province_name' => 'vnap_province', 'ward_name' => 'vnap_ward', 'address_name' => 'vnap_address',
            'province_hidden_name' => 'vnap_province_name', 'ward_hidden_name' => 'vnap_ward_name',
            'province_label' => __( 'Tỉnh / Thành phố', 'vn-address-pro' ),
            'ward_label' => __( 'Phường / Xã / Đặc khu', 'vn-address-pro' ),
            'address_label' => __( 'Số nhà, tên đường', 'vn-address-pro' ),
            'province_placeholder' => __( 'Chọn Tỉnh / Thành phố', 'vn-address-pro' ),
            'ward_placeholder' => __( 'Chọn Phường / Xã / Đặc khu', 'vn-address-pro' ),
            'address_placeholder' => __( 'Số nhà, tên đường', 'vn-address-pro' ),
            'default_province' => '', 'default_ward' => '', 'default_address' => '',
            'show_address' => 'yes', 'required' => 'no', 'province_required' => '', 'ward_required' => '', 'address_required' => '', 'class' => '',
        );
        $config = array();
        foreach ( $defaults as $key => $fallback ) {
            $value = $args[ $key ] ?? $fallback;
            if ( is_bool( $value ) && in_array( $key, array( 'show_address','required','province_required','ward_required','address_required' ), true ) ) { $value = $value ? 'yes' : 'no'; }
            $config[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $fallback;
        }
        foreach ( array( 'province_name','ward_name','address_name','province_hidden_name','ward_hidden_name' ) as $key ) {
            if ( ! preg_match( '/\A[A-Za-z_][A-Za-z0-9_-]*(?:\[[A-Za-z0-9_-]*\])*\z/', $config[ $key ] ) ) { $config[ $key ] = $defaults[ $key ]; }
        }
        foreach ( array( 'default_province', 'default_ward' ) as $key ) {
            $config[ $key ] = preg_match( '/\A[0-9]+\z/', $config[ $key ] ) && (int) $config[ $key ] > 0 ? (string) (int) $config[ $key ] : '';
        }
        $truthy = static fn( $v ) => in_array( strtolower( $v ), array( 'yes','true','1' ), true );
        $config['show_address'] = $truthy( $config['show_address'] );
        foreach ( array( 'province_required','ward_required','address_required' ) as $key ) {
            $config[ $key ] = $truthy( $config[ $key ] === '' ? $config['required'] : $config[ $key ] );
        }
        $classes = array();
        foreach ( preg_split( '/\s+/', trim( $config['class'] ) ) as $class ) {
            $class = sanitize_html_class( $class );
            if ( $class !== '' ) { $classes[] = str_starts_with( $class, 'vnap-' ) ? $class : 'vnap-' . $class; }
        }
        $config['class'] = implode( ' ', array_unique( $classes ) );
        return $config;
    }
}
