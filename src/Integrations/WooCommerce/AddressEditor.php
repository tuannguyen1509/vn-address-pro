<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

/** Shared presentation/configuration only; canonical validation stays in AddressValidator. */
final class AddressEditor {
    public static function options( string $country, string $state, $code, string $prefix ): array {
        $options = array( '' => __( 'Chọn Phường / Xã / Đặc khu', 'vn-address-pro' ) );
        $selection = ( new AddressValidator() )->resolve( array( $prefix . '_country' => $country, $prefix . '_state' => $state, AddressValidator::field( $prefix ) => $code ), $prefix );
        if ( is_array( $selection ) ) { $options[ $selection['ward']['code'] ] = $selection['ward']['name']; }
        return $options;
    }
    public static function enqueue( string $mode ): void {
        \Tunlamweb\VNAddressPro\Frontend\Assets::register();
        $codes = array();
        foreach ( WC()->countries->get_states( 'VN' ) ?: array() as $key => $name ) {
            $code = States::from_wc_state_code( (string) $key );
            if ( $code !== null ) { $codes[ $key ] = (string) $code; }
        }
        wp_enqueue_script( 'vnap-address-management', VNAP_URL . 'assets/js/woocommerce-address-management.js', array( 'jquery','vnap-address-data' ), VNAP_VERSION, true );
        wp_localize_script( 'vnap-address-management', 'VNAddressProEditorConfig', array(
            'mode' => $mode, 'stateCodes' => $codes,
            'placeholder' => __( 'Chọn Phường / Xã / Đặc khu', 'vn-address-pro' ),
            'loading' => __( 'Đang tải danh sách Phường / Xã...', 'vn-address-pro' ),
            'error' => __( 'Không thể tải dữ liệu. Chọn lại tỉnh để thử lại.', 'vn-address-pro' ),
            'obsolete' => __( 'Chưa có mã phường/xã hợp lệ trong dữ liệu hiện tại. Vui lòng chọn lại khi sửa địa chỉ.', 'vn-address-pro' ),
        ) );
    }
}
