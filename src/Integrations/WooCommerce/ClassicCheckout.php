<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class ClassicCheckout {
    private array $resolved = array();
    public function register(): void {
        add_filter( 'woocommerce_checkout_posted_data', array( $this, 'normalize' ), 20 );
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 20, 2 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'order' ), 20, 2 );
        add_action( 'woocommerce_checkout_update_customer', array( $this, 'customer' ), 20, 2 );
    }
    public function normalize( array $data ): array {
        $this->resolved = array();
        if ( ! Compatibility::request() ) { return $data; }
        if ( empty( $data['ship_to_different_address'] ) && isset( $data['shipping_country'] ) ) {
            $data[ AddressValidator::field( 'shipping' ) ] = $data[ AddressValidator::field( 'billing' ) ] ?? '';
        }
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            if ( ! isset( $data[ $prefix . '_country' ] ) ) { continue; }
            $selection = ( new AddressValidator() )->resolve( $data, $prefix );
            $this->resolved[ $prefix ] = $selection;
            if ( is_wp_error( $selection ) ) {
                // Preserve the error, but keep hostile arrays away from Woo's scalar field validators.
                foreach ( array( $prefix . '_state', $prefix . '_city', AddressValidator::field( $prefix ) ) as $key ) {
                    if ( isset( $data[ $key ] ) && ! is_scalar( $data[ $key ] ) ) { $data[ $key ] = ''; }
                }
            }
            if ( is_array( $selection ) ) {
                $data[ $prefix . '_state' ] = States::to_wc_state_code( $selection['province']['code'] );
                $data[ $prefix . '_city' ] = $selection['ward']['name'];
                $data[ AddressValidator::field( $prefix ) ] = (string) $selection['ward']['code'];
            }
        }
        return $data;
    }
    public function validate( array $data, \WP_Error $errors ): void {
        if ( ! Compatibility::request() ) { return; }
        foreach ( $this->resolved as $prefix => $selection ) {
            if ( is_wp_error( $selection ) ) {
                $errors->add( 'vnap_' . $prefix . '_address', sprintf( __( '%1$s: %2$s', 'vn-address-pro' ), $prefix === 'billing' ? __( 'Địa chỉ thanh toán', 'vn-address-pro' ) : __( 'Địa chỉ giao hàng', 'vn-address-pro' ), __( 'Vui lòng chọn Tỉnh / Thành phố và Phường / Xã hợp lệ, cùng địa phương.', 'vn-address-pro' ) ), array( 'id' => $prefix . '_city' ) );
            }
        }
    }
    public function order( \WC_Order $order, array $data ): void {
        if ( Compatibility::request() ) { ( new OrderAddress() )->write( $order, $this->resolved ); }
    }
    public function customer( \WC_Customer $customer, array $data ): void {
        if ( Compatibility::request() ) { ( new CustomerAddress() )->write( $customer, $this->resolved ); }
    }
}
