<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms;
use Tunlamweb\VNAddressPro\Plugin;
defined( 'ABSPATH' ) || exit;

final class Validator {
    public function validate( $error, $field, $data, $fields, $form ) {
        // Fluent passes only active essential fields, including parent-container conditions.
        $raw = $field['raw'] ?? array();
        if ( ! Configuration::valid( $raw, $form ) ) { return array( __( 'Invalid address field configuration.', 'vn-address-pro' ) ); }
        $name = $raw['attributes']['name']; $value = $data[$name] ?? '';
        if ( ! is_string( $value ) && ! is_int( $value ) ) { return $this->invalid(); }
        if ( $value === '' ) {
            if ( ! empty( $raw['settings']['validation_rules']['required']['value'] ) ) { return $error ?: $this->invalid(); }
            if ( $raw['element'] === 'vnap_province' ) { return $error; }
        }
        $service = Plugin::instance()->address_service();
        if ( $raw['element'] === 'vnap_ward' ) {
            $reference = $raw['settings']['vnap_province_field'];
            // An active Ward may not depend on an inactive Province.
            if ( ! isset( $fields[$reference] ) ) { return $this->invalid(); }
            $province = $data[$reference] ?? '';
            if ( $province === '' && $value === '' ) { return $error; }
            return is_wp_error( $service->validate_selection( $province, $value ) ) ? $this->invalid() : $error;
        }
        if ( $value === '' ) { return $error; }
        $rows = $service->get_provinces();
        if ( is_wp_error( $rows ) ) { return $this->invalid(); }
        foreach ( $rows as $row ) { if ( (string) $row['code'] === (string) $value ) { return $error; } }
        return $this->invalid();
    }
    private function invalid(): array { return array( __( 'Please select a valid province and ward.', 'vn-address-pro' ) ); }
}
