<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7;
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Integrations\Forms\AddressSubmission;
defined( 'ABSPATH' ) || exit;

final class Validator {
    public function selection( \WPCF7_FormTag $tag, array $data ): array|\WP_Error {
        $config = FormTags::config( $tag ); if ( is_wp_error( $config ) ) { return $config; }
        $value = $data[$tag->name] ?? '';
        if ( ! is_string( $value ) ) { return $this->invalid(); }
        if ( $config->kind === 'address' ) {
            $length = preg_match_all( '/./us', $value );
            return ( $config->required && trim( $value ) === '' ) || $length === false || $length > 400 ? $this->invalid() : array( 'value' => $value );
        }
        if ( $config->kind === 'province' ) {
            if ( $value === '' ) { return $config->required ? $this->invalid() : array(); }
            if ( ! preg_match( '/\A[1-9][0-9]*\z/', $value ) || (string) (int) $value !== $value ) { return $this->invalid(); }
            $rows = Plugin::instance()->address_service()->get_provinces(); if ( is_wp_error( $rows ) ) { return $rows; }
            foreach ( $rows as $row ) { if ( (string) $row['code'] === $value ) { return array( 'province' => $row ); } }
            return $this->invalid();
        }
        $province = $data[$config->group] ?? '';
        if ( ! is_string( $province ) ) { return $this->invalid(); }
        if ( $province === '' && $value === '' && ! $config->required ) { return array(); }
        if ( ! preg_match( '/\A[1-9][0-9]*\z/', $value ) || ! preg_match( '/\A[1-9][0-9]*\z/', $province ) ) { return $this->invalid(); }
        return ( new AddressSubmission( $province, $value ) )->resolve( Plugin::instance()->address_service() );
    }
    public function validate( \WPCF7_Validation $result, \WPCF7_FormTag $tag ): \WPCF7_Validation {
        $submission = \WPCF7_Submission::get_instance();
        if ( ! $submission ) { $result->invalidate( $tag, $this->invalid() ); return $result; }
        $selection = $this->selection( $tag, $submission->get_posted_data() );
        if ( is_wp_error( $selection ) ) { $result->invalidate( $tag, $selection ); }
        return $result;
    }
    private function invalid(): \WP_Error { return new \WP_Error( 'vnap_form_selection', __( 'Please enter a valid value for this address field.', 'vn-address-pro' ) ); }
}
