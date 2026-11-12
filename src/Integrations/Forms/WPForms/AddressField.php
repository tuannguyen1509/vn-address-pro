<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\WPForms;
use Tunlamweb\VNAddressPro\Frontend\AddressComponent;
use Tunlamweb\VNAddressPro\Plugin;
defined( 'ABSPATH' ) || exit;

/** Loaded only after native WPForms base class is available. One independent address group. */
final class AddressField extends \WPForms_Field {
    public function init() {
        $this->name = __( 'Địa chỉ Việt Nam', 'vn-address-pro' );
        $this->type = 'vnap-address'; $this->icon = 'fa-map-marker'; $this->order = 190;
        $this->keywords = 'Vietnam province ward address tỉnh xã';
    }
    public function field_options( $field ) {
        $this->field_option( 'basic-options', $field, array( 'markup' => 'open' ) );
        foreach ( array( 'label','description','required' ) as $option ) { $this->field_option( $option, $field ); }
        $this->field_option( 'basic-options', $field, array( 'markup' => 'close' ) );
    }
    public function field_preview( $field ) {
        $this->field_preview_option( 'label', $field );
        echo '<select disabled><option>' . esc_html__( 'Tỉnh / Thành phố', 'vn-address-pro' ) . '</option></select><select disabled><option>' . esc_html__( 'Phường / Xã / Đặc khu', 'vn-address-pro' ) . '</option></select>';
        $this->field_preview_option( 'description', $field );
    }
    public function field_display( $field, $deprecated, $form_data ) {
        $id = absint( $field['id'] ); $name = 'wpforms[fields][' . $id . ']';
        echo AddressComponent::render( array( 'province_name' => $name . '[province]', 'ward_name' => $name . '[ward]', 'province_hidden_name' => $name . '[province_name]', 'ward_hidden_name' => $name . '[ward_name]', 'show_address' => false, 'required' => ! empty( $field['required'] ) ) ); // Escaped by the shared renderer.
    }
    public function resolve( $submitted, bool $required ): array|\WP_Error|null {
        if ( $submitted === '' || $submitted === null ) { return $required ? $this->error() : null; }
        if ( ! is_array( $submitted ) ) { return $this->error(); }
        $p = $submitted['province'] ?? ''; $w = $submitted['ward'] ?? '';
        if ( ! ( is_string( $p ) || is_int( $p ) ) || ! ( is_string( $w ) || is_int( $w ) ) ) { return $this->error(); }
        if ( (string) $p === '' && (string) $w === '' ) { return $required ? $this->error() : null; }
        // Resolve display names on the server. Hidden names are never authoritative.
        return Plugin::instance()->address_service()->validate_selection( $p, $w );
    }
    public function validate( $field_id, $field_submit, $form_data ) {
        $result = $this->resolve( $field_submit, ! empty( $form_data['fields'][ $field_id ]['required'] ) );
        if ( is_wp_error( $result ) ) { wpforms()->obj( 'process' )->errors[ $form_data['id'] ][ $field_id ] = $result->get_error_message(); }
    }
    public function format( $field_id, $field_submit, $form_data ) {
        $result = $this->resolve( $field_submit, ! empty( $form_data['fields'][ $field_id ]['required'] ) );
        if ( is_wp_error( $result ) ) { $this->validate( $field_id, $field_submit, $form_data ); return; }
        $p = $result ? (string) $result['province']['code'] : ''; $w = $result ? (string) $result['ward']['code'] : '';
        wpforms()->obj( 'process' )->fields[ $field_id ] = array(
            'name' => sanitize_text_field( $form_data['fields'][ $field_id ]['label'] ?? '' ), 'id' => wpforms_validate_field_id( $field_id ), 'type' => $this->type,
            'value' => $result ? sanitize_text_field( $result['ward']['name'] . ', ' . $result['province']['name'] ) : '',
            'value_raw' => wp_json_encode( array( 'province_code' => $p, 'ward_code' => $w ) ), 'province_code' => $p, 'ward_code' => $w,
        );
    }
    private function error(): \WP_Error { return new \WP_Error( 'vnap_wpforms_selection', __( 'Vui lòng chọn Tỉnh / Thành phố và Phường / Xã hợp lệ.', 'vn-address-pro' ) ); }
}
