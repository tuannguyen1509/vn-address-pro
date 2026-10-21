<?php
namespace Tunlamweb\VNAddressPro\Frontend;
defined( 'ABSPATH' ) || exit;

final class FieldRenderer {
    public function render( array $c, array|\WP_Error $provinces ): string {
        $id = wp_unique_id( 'vnap-address-' ); $selected = '';
        $html = '<div id="' . esc_attr( $id ) . '" class="vnap-address ' . esc_attr( $c['class'] ) . '" data-vnap-address data-vnap-default-ward="' . esc_attr( $c['default_ward'] ) . '">';
        $html .= $this->open( $id, 'province', $c );
        $html .= '<select class="vnap-select" data-vnap-province id="' . esc_attr( $id . '-province' ) . '" name="' . esc_attr( $c['province_name'] ) . '"' . $this->attributes( $id, $c['province_required'] ) . ( is_wp_error( $provinces ) ? ' disabled' : '' ) . '>';
        $html .= '<option value="">' . esc_html( $c['province_placeholder'] ) . '</option>';
        if ( ! is_wp_error( $provinces ) ) {
            foreach ( $provinces as $province ) {
                if ( (string) $province['code'] === $c['default_province'] ) { $selected = $province['name']; }
                $html .= '<option value="' . esc_attr( $province['code'] ) . '"' . selected( (string) $province['code'], $c['default_province'], false ) . '>' . esc_html( $province['name'] ) . '</option>';
            }
        }
        $html .= '</select></div>' . $this->open( $id, 'ward', $c );
        $html .= '<select class="vnap-select" data-vnap-ward id="' . esc_attr( $id . '-ward' ) . '" name="' . esc_attr( $c['ward_name'] ) . '" disabled' . $this->attributes( $id, $c['ward_required'] ) . '><option value="">' . esc_html( $c['ward_placeholder'] ) . '</option></select></div>';
        $html .= '<input type="hidden" data-vnap-province-name name="' . esc_attr( $c['province_hidden_name'] ) . '" value="' . esc_attr( $selected ) . '"><input type="hidden" data-vnap-ward-name name="' . esc_attr( $c['ward_hidden_name'] ) . '" value="">';
        if ( $c['show_address'] ) {
            $html .= $this->open( $id, 'address', $c ) . '<input type="text" class="vnap-input" data-vnap-address-line id="' . esc_attr( $id . '-address' ) . '" name="' . esc_attr( $c['address_name'] ) . '" placeholder="' . esc_attr( $c['address_placeholder'] ) . '" value="' . esc_attr( $c['default_address'] ) . '" autocomplete="address-line1"' . $this->attributes( $id, $c['address_required'] ) . '></div>';
        }
        $html .= '<p class="vnap-status" id="' . esc_attr( $id . '-status' ) . '" data-vnap-status role="status" aria-live="polite"></p>';
        $html .= '<p class="vnap-error" id="' . esc_attr( $id . '-error' ) . '" data-vnap-error role="alert">' . ( is_wp_error( $provinces ) ? esc_html__( 'Không thể tải danh sách Tỉnh / Thành phố.', 'vn-address-pro' ) : '' ) . '</p>';
        $html .= '<button type="button" class="vnap-retry" data-vnap-retry hidden>' . esc_html__( 'Thử lại', 'vn-address-pro' ) . '</button>';
        $html .= '<noscript><p>' . esc_html__( 'Vui lòng bật JavaScript để chọn Phường / Xã.', 'vn-address-pro' ) . '</p></noscript></div>';
        return $html;
    }
    private function open( string $id, string $field, array $c ): string {
        return '<div class="vnap-field"><label class="vnap-label" for="' . esc_attr( $id . '-' . $field ) . '">' . esc_html( $c[ $field . '_label' ] ) . '</label>';
    }
    private function attributes( string $id, bool $required ): string {
        return ' aria-describedby="' . esc_attr( $id . '-status ' . $id . '-error' ) . '"' . ( $required ? ' required aria-required="true"' : '' );
    }
}
