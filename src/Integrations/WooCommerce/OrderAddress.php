<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class OrderAddress {
    public function register(): void {
        add_filter( 'woocommerce_localisation_address_formats', array( $this, 'formats' ) );
        add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'billing' ), 10, 2 );
        add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'shipping' ), 10, 2 );
    }
    public function formats( array $formats ): array {
        // Woo's default VN format omits state. Include it once for readable province snapshots.
        $vn = $formats['VN'] ?? $formats['default'];
        if ( ! preg_match( '/\{state(?:_code|_upper)?\}/', $vn ) ) {
            $formats['VN'] = str_contains( $vn, '{country}' ) ? str_replace( '{country}', "{state}\n{country}", $vn ) : $vn . "\n{state}";
        }
        return $formats;
    }
    public function write( \WC_Order $order, array $selections ): void {
        foreach ( $selections as $prefix => $selection ) {
            if ( is_wp_error( $selection ) ) { throw new \Exception( __( 'Địa chỉ Việt Nam chưa được xác thực.', 'vn-address-pro' ) ); }
            foreach ( array( 'province_code','ward_code','province_name' ) as $part ) { $order->delete_meta_data( AddressValidator::meta( $prefix, $part ) ); }
            if ( ! is_array( $selection ) ) { continue; }
            $order->update_meta_data( AddressValidator::meta( $prefix, 'province_code' ), $selection['province']['code'] );
            $order->update_meta_data( AddressValidator::meta( $prefix, 'ward_code' ), $selection['ward']['code'] );
            $order->update_meta_data( AddressValidator::meta( $prefix, 'province_name' ), $selection['province']['name'] );
            $order->{"set_{$prefix}_state"}( States::to_wc_state_code( $selection['province']['code'] ) );
            $order->{"set_{$prefix}_city"}( $selection['ward']['name'] );
        }
        // Woo saves once in its create_order lifecycle.
    }
    public function billing( array $address, \WC_Order $order ): array { return $this->snapshot( $address, $order, 'billing' ); }
    public function shipping( array $address, \WC_Order $order ): array { return $this->snapshot( $address, $order, 'shipping' ); }
    private function snapshot( array $address, \WC_Order $order, string $prefix ): array {
        $name = $order->get_meta( AddressValidator::meta( $prefix, 'province_name' ) );
        if ( ( $address['country'] ?? '' ) === 'VN' && is_string( $name ) && $name !== '' ) { $address['state'] = $name; }
        return $address;
    }
}
