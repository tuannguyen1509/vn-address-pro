<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class CustomerAddress {
    public function register(): void { add_filter( 'woocommerce_checkout_get_value', array( $this, 'value' ), 10, 2 ); }
    public function value( $value, string $key ) {
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            if ( $key === AddressValidator::field( $prefix ) && $value === null && WC()->customer ) {
                return WC()->customer->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) );
            }
        }
        return $value;
    }
    public function write( \WC_Customer $customer, array $selections ): void {
        foreach ( $selections as $prefix => $selection ) {
            if ( is_wp_error( $selection ) ) { throw new \Exception( __( 'Địa chỉ Việt Nam chưa được xác thực.', 'vn-address-pro' ) ); }
            foreach ( array( 'province_code','ward_code' ) as $part ) { $customer->delete_meta_data( AddressValidator::meta( $prefix, $part ) ); }
            if ( is_array( $selection ) ) {
                $customer->update_meta_data( AddressValidator::meta( $prefix, 'province_code' ), $selection['province']['code'] );
                $customer->update_meta_data( AddressValidator::meta( $prefix, 'ward_code' ), $selection['ward']['code'] );
                $customer->{"set_{$prefix}_state"}( States::to_wc_state_code( $selection['province']['code'] ) );
                $customer->{"set_{$prefix}_city"}( $selection['ward']['name'] );
            }
        }
        // WC process_customer performs the save, including newly registered accounts.
    }
}
