<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms;
use Tunlamweb\VNAddressPro\Plugin;
defined( 'ABSPATH' ) || exit;

/** Display only: never replaces Fluent's stored response or raw .value smartcode. */
final class Formatter {
    public function format( $value, $field, $form_id, $html = false ) {
        if ( ! is_scalar( $value ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $value ) || (string) (int) $value !== (string) $value ) { return ''; }
        $plugin = Plugin::instance(); $name = '';
        if ( ( $field['element'] ?? '' ) === 'vnap_province' ) {
            $rows = $plugin->address_service()->get_provinces();
            if ( ! is_wp_error( $rows ) ) { foreach ( $rows as $row ) { if ( (string) $row['code'] === (string) $value ) { $name = $row['name']; break; } } }
        } else {
            // Indexed local lookup, never a scan/download of all wards.
            $ward = $plugin->repository()->get_ward( (int) $value );
            if ( ! is_wp_error( $ward ) && ! empty( $ward['province_code'] ) ) {
                $selection = $plugin->address_service()->validate_selection( $ward['province_code'], $value );
                if ( ! is_wp_error( $selection ) ) { $name = $selection['ward']['name']; }
            }
        }
        // Historical/removed codes remain visible; current names are never supplied by the browser.
        $name = $name ?: (string) $value;
        return $html ? esc_html( $name ) : $name;
    }
}
