<?php
namespace Tunlamweb\VNAddressPro\Rest\V1;

defined( 'ABSPATH' ) || exit;

/** Frozen v1 messages/statuses, intentionally independent of internal error messages. */
final class ErrorMapper {
    public static function map( \WP_Error $error ): \WP_Error {
        $data = $error->get_error_data();
        $data = is_array( $data ) ? $data : array();
        switch ( $error->get_error_code() ) {
            case 'vnap_transport_error':
                return new \WP_Error(
                    'vn_address_upstream_error',
                    'Không thể kết nối nguồn dữ liệu địa giới hành chính.',
                    array( 'status' => 502, 'detail' => (string) ( $data['detail'] ?? '' ) )
                );
            case 'vnap_remote_http_error':
                return new \WP_Error(
                    'vn_address_upstream_http_error',
                    'Nguồn dữ liệu trả về lỗi HTTP.',
                    array( 'status' => 502, 'upstream_status' => (int) ( $data['upstream_status'] ?? 0 ) )
                );
            case 'vnap_not_found':
                return new \WP_Error( 'vn_address_not_found', 'Không tìm thấy tỉnh/thành phố.', array( 'status' => 404 ) );
            default:
                return new \WP_Error( 'vn_address_invalid_json', 'Dữ liệu JSON từ nguồn không hợp lệ.', array( 'status' => 502 ) );
        }
    }
}
