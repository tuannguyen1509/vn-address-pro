<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7;
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Frontend\Assets as CoreAssets;
defined( 'ABSPATH' ) || exit;

final class Renderer {
    public function render( \WPCF7_FormTag $tag ): string {
        if ( ! $tag->name ) { return ''; }
        $config = FormTags::config( $tag ); $error = wpcf7_get_validation_error( $tag->name );
        $bad = is_wp_error( $config );
        if ( $bad ) { $error .= '<span class="wpcf7-not-valid-tip" aria-hidden="true">' . esc_html( $config->get_error_message() ) . '</span>'; }
        $id = $tag->get_id_option() ?: wp_unique_id( 'vnap-cf7-' );
        $kind = substr( $tag->basetype, 5 );
        $atts = array( 'id' => $id, 'name' => $tag->name, 'class' => $tag->get_class_option( wpcf7_form_controls_class( $tag->type ) . ' vnap-cf7-field' ),
            'aria-invalid' => $error ? 'true' : 'false', 'aria-required' => $tag->is_required() ? 'true' : 'false', 'data-vnap-cf7-kind' => $kind );
        if ( $error && function_exists( 'wpcf7_get_validation_error_reference' ) ) { $atts['aria-describedby'] = wpcf7_get_validation_error_reference( $tag->name ); }
        $default = $bad ? '' : $config->default; $default = wpcf7_get_hangover( $tag->name, $default );
        $default = is_scalar( $default ) ? (string) $default : '';
        if ( $kind === 'address' ) {
            $atts += array( 'type' => 'text', 'value' => $default, 'maxlength' => 400, 'placeholder' => $bad ? '' : $config->placeholder );
            $html = '<input ' . wpcf7_format_atts( $atts ) . ' />';
        } else {
            $placeholder = $bad || $config->placeholder === '' ? __( 'Please select an address option.', 'vn-address-pro' ) : $config->placeholder;
            $options = '<option value="">' . esc_html( $placeholder ) . '</option>';
            if ( $kind === 'province' ) {
                $rows = Plugin::instance()->address_service()->get_provinces();
                if ( is_wp_error( $rows ) ) { $atts['disabled'] = true; $error .= '<span class="wpcf7-not-valid-tip">' . esc_html__( 'Address data is temporarily unavailable.', 'vn-address-pro' ) . '</span>'; }
                else { foreach ( $rows as $row ) { $options .= '<option value="' . esc_attr( $row['code'] ) . '" ' . selected( (string) $row['code'], $default, false ) . '>' . esc_html( $row['name'] ) . '</option>'; } }
            } else {
                $atts['disabled'] = true; $atts['data-vnap-cf7-province'] = $bad ? '' : $config->group; $atts['data-vnap-default-ward'] = $default;
                $atts['aria-describedby'] = trim( ( $atts['aria-describedby'] ?? '' ) . ' ' . $id . '-status ' . $id . '-error' );
            }
            if ( $bad ) { $atts['disabled'] = true; }
            $html = '<select ' . wpcf7_format_atts( $atts ) . '>' . $options . '</select>';
        }
        if ( $kind === 'ward' ) {
            $status_id = $id . '-status'; $error_id = $id . '-error';
            $html .= '<span id="' . esc_attr( $status_id ) . '" data-vnap-status role="status" aria-live="polite"></span><span id="' . esc_attr( $error_id ) . '" data-vnap-error role="alert"></span><button type="button" data-vnap-retry hidden>' . esc_html__( 'Retry loading wards', 'vn-address-pro' ) . '</button>';
        }
        if ( ! is_admin() ) {
            CoreAssets::enqueue();
            wp_enqueue_script( 'vnap-contact-form-7', VNAP_URL . 'assets/js/contact-form-7.js', array( CoreAssets::HANDLE ), VNAP_VERSION, true );
        }
        return '<span class="wpcf7-form-control-wrap vnap-cf7-wrap" data-name="' . esc_attr( $tag->name ) . '">' . $html . $error . '</span>';
    }
}
