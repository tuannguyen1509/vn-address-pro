<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms;

use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Frontend\Assets;
defined( 'ABSPATH' ) || exit;

/** Both native field types share rendering; Fluent supplies builder and conditional wrappers. */
final class AddressSelect extends \FluentForm\App\Services\FormBuilder\BaseFieldManager {
    private string $kind;
    public function __construct( string $kind ) {
        $this->kind = $kind;
        parent::__construct( 'vnap_' . $kind, $kind === 'province' ? __( 'VN Province', 'vn-address-pro' ) : __( 'VN Ward', 'vn-address-pro' ), array( 'vietnam', 'address' ), 'advanced' );
    }
    public function getComponent() {
        return array(
            'index' => 90, 'element' => $this->key,
            'attributes' => array( 'name' => $this->key, 'value' => '', 'id' => '', 'class' => '' ),
            'settings' => array(
                'label' => $this->kind === 'province' ? __( 'Tỉnh / Thành phố', 'vn-address-pro' ) : __( 'Phường / Xã / Đặc khu', 'vn-address-pro' ),
                'admin_field_label' => '', 'container_class' => '', 'help_message' => '', 'label_placement' => '',
                'placeholder' => __( 'Please select an address option.', 'vn-address-pro' ), 'vnap_province_field' => '',
                'validation_rules' => array( 'required' => array( 'value' => false, 'message' => __( 'This field is required.', 'vn-address-pro' ), 'global' => false ) ),
                'conditional_logics' => array(),
            ),
            'options' => array(),
            'editor_options' => array( 'title' => $this->title, 'icon_class' => 'icon-caret-square-o-down', 'template' => 'select' ),
        );
    }
    public function getGeneralEditorElements() {
        $keys = parent::getGeneralEditorElements();
        if ( $this->kind === 'ward' ) { $keys[] = 'vnap_province_field'; }
        return $keys;
    }
    public function getEditorCustomizationSettings() {
        return array( 'vnap_province_field' => array( 'template' => 'inputText', 'label' => __( 'Province Field', 'vn-address-pro' ),
            'help_text' => __( 'Exact Name Attribute of the VN Province field in this form. Update this reference when renaming that field.', 'vn-address-pro' ) ) );
    }
    public function render( $element, $form ) {
        $valid = Configuration::valid( $element, $form );
        $name = $element['attributes']['name'] ?? '';
        $id = $this->makeElementId( $element, $form );
        $element['attributes']['id'] = $id;
        $element['settings']['container_class'] = trim( ( $element['settings']['container_class'] ?? '' ) . ' vnap-fluent-wrap' );
        $required = ! empty( $element['settings']['validation_rules']['required']['value'] );
        $attributes = array( 'id' => $id, 'name' => Configuration::name( $name ) ? $name : '', 'class' => 'ff-el-form-control ' . ( $element['attributes']['class'] ?? '' ),
            'data-vnap-fluent-kind' => $this->kind, 'aria-invalid' => 'false', 'aria-required' => $required ? 'true' : 'false', 'aria-labelledby' => 'label_' . $id );
        $options = '<option value="">' . esc_html( $element['settings']['placeholder'] ?? '' ) . '</option>';
        if ( $this->kind === 'province' && $valid ) {
            $rows = Plugin::instance()->address_service()->get_provinces();
            if ( is_wp_error( $rows ) ) { $valid = false; }
            else { foreach ( $rows as $row ) { $options .= '<option value="' . esc_attr( $row['code'] ) . '">' . esc_html( $row['name'] ) . '</option>'; } }
        }
        if ( $this->kind === 'ward' ) {
            $attributes['data-vnap-fluent-province'] = $valid ? $element['settings']['vnap_province_field'] : '';
            $attributes['disabled'] = 'disabled';
        }
        if ( ! $valid ) { $attributes['disabled'] = 'disabled'; }
        $html = '<select ' . $this->buildAttributes( $attributes ) . '>' . $options . '</select>';
        if ( $this->kind === 'ward' ) {
            $html .= '<span data-vnap-status role="status" aria-live="polite"></span><span data-vnap-error role="alert"></span><button type="button" data-vnap-retry hidden>' . esc_html__( 'Retry loading wards', 'vn-address-pro' ) . '</button>';
        }
        if ( ! $valid ) { $html .= '<span role="alert">' . esc_html__( 'Address field configuration or data is unavailable.', 'vn-address-pro' ) . '</span>'; }
        echo $this->buildElementMarkup( $html, $element, $form ); // Native wrapper escapes configuration; markup above is escaped.
        if ( ! is_admin() ) {
            Assets::enqueue();
            wp_enqueue_script( 'vnap-fluent-forms', VNAP_URL . 'assets/js/fluent-forms.js', array( Assets::HANDLE, 'jquery' ), VNAP_VERSION, true );
        }
    }
}
