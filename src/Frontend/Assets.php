<?php
namespace Tunlamweb\VNAddressPro\Frontend;
defined( 'ABSPATH' ) || exit;

final class Assets {
    public const HANDLE = 'vnap-address';
    public static function enqueue(): void {
        if ( is_admin() ) { return; }
        if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
            wp_register_script( self::HANDLE, VNAP_URL . 'assets/js/vn-address.js', array(), VNAP_VERSION, true );
            wp_localize_script( self::HANDLE, 'VNAddressProConfig', array(
                'wardsUrl' => rest_url( 'vn-address/v1/wards' ),
                'strings' => array(
                    'loading' => __( 'Đang tải danh sách Phường / Xã...', 'vn-address-pro' ),
                    'loaded' => __( 'Đã tải danh sách Phường / Xã.', 'vn-address-pro' ),
                    'empty' => __( 'Không có dữ liệu Phường / Xã.', 'vn-address-pro' ),
                    'error' => __( 'Không thể tải danh sách Phường / Xã. Vui lòng thử lại.', 'vn-address-pro' ),
                    'invalidWard' => __( 'Phường / Xã không thuộc Tỉnh / Thành phố đã chọn.', 'vn-address-pro' ),
                    'required' => __( 'Vui lòng điền trường bắt buộc.', 'vn-address-pro' ),
                    'chooseProvince' => __( 'Vui lòng chọn Tỉnh / Thành phố trước.', 'vn-address-pro' ),
                    'retry' => __( 'Thử lại', 'vn-address-pro' ),
                ),
            ) );
        }
        wp_enqueue_script( self::HANDLE );
        wp_enqueue_style( self::HANDLE, VNAP_URL . 'assets/css/vn-address.css', array(), VNAP_VERSION );
    }
}
