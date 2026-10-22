<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce\MyAccount;

use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressEditor;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\CustomerAddress;
defined( 'ABSPATH' ) || exit;

final class MyAccountIntegration {
    public function register(): void {
        add_filter( 'woocommerce_address_to_edit', array( $this, 'fields' ), 20, 2 );
        add_action( 'wp_loaded', array( $this, 'preflight' ), 19 );
        add_action( 'woocommerce_after_save_address_validation', array( $this, 'validate' ), 20, 4 );
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            add_filter( 'woocommerce_process_myaccount_field_' . $prefix . '_city', function ( $value ) use ( $prefix ) {
                $selection = ( new AddressValidator() )->resolve( wp_unslash( $_POST ), $prefix );
                return is_array( $selection ) ? $selection['ward']['name'] : $value;
            } );
        }
        add_action( 'wp_enqueue_scripts', static function () {
            if ( is_account_page() && is_wc_endpoint_url( 'edit-address' ) && is_user_logged_in() ) { AddressEditor::enqueue( 'account' ); }
        } );
    }
    public function fields( array $fields, string $prefix ): array {
        if ( ! in_array( $prefix, array( 'billing','shipping' ), true ) ) { return $fields; }
        $customer = new \WC_Customer( get_current_user_id() );
        $code = $customer->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) );
        if ( isset( $_POST[ AddressValidator::field( $prefix ) ] ) && is_string( $_POST[ AddressValidator::field( $prefix ) ] ) ) { $code = wp_unslash( $_POST[ AddressValidator::field( $prefix ) ] ); }
        $country = $customer->{"get_{$prefix}_country"}(); $state = $customer->{"get_{$prefix}_state"}();
        $fields[ AddressValidator::field( $prefix ) ] = array( 'type' => 'hidden', 'value' => is_scalar( $code ) ? (string) $code : '', 'required' => false, 'priority' => 69 );
        $fields[ 'vnap_account_' . $prefix . '_ward' ] = array(
            'type' => 'select', 'label' => __( 'Phường / Xã / Đặc khu', 'vn-address-pro' ), 'class' => array( 'form-row-wide','vnap-editor-ward' ),
            'options' => AddressEditor::options( $country, $state, $code, $prefix ), 'value' => is_scalar( $code ) ? (string) $code : '', 'priority' => 71, 'required' => false,
        );
        uasort( $fields, 'wc_checkout_fields_uasort_comparison' );
        return $fields;
    }
    public function preflight(): void {
        $nonce = $_REQUEST['woocommerce-edit-address-nonce'] ?? $_REQUEST['_wpnonce'] ?? '';
        if ( ( $_POST['action'] ?? '' ) !== 'edit_address' || ! is_user_logged_in() || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'woocommerce-edit_address' ) ) { return; }
        // Native Woo processes these before the validation hook; reject arrays without letting
        // its string-oriented country/field setters emit warnings or a TypeError.
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            foreach ( array( 'country','state','city' ) as $part ) {
                $key = $prefix . '_' . $part;
                if ( isset( $_POST[ $key ] ) && ! is_string( $_POST[ $key ] ) ) {
                    wc_add_notice( __( 'Dữ liệu địa chỉ không hợp lệ.', 'vn-address-pro' ), 'error' );
                    $_POST[ $key ] = '';
                }
            }
        }
    }
    public function validate( $user_id, $prefix, $fields, $customer ): void {
        if ( ! in_array( $prefix, array( 'billing','shipping' ), true ) || ! $customer instanceof \WC_Customer ) { return; }
        $selection = ( new AddressValidator() )->resolve( wp_unslash( $_POST ), $prefix );
        if ( is_wp_error( $selection ) ) { wc_add_notice( $selection->get_error_message(), 'error' ); return; }
        if ( wc_notice_count( 'error' ) === 0 ) { ( new CustomerAddress() )->write( $customer, array( $prefix => $selection ) ); }
        // Woo saves this exact object once after all validation callbacks pass.
    }
}
