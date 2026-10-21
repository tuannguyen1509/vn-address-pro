<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class AddressFields {
    public function register(): void {
        add_filter( 'woocommerce_get_country_locale', array( $this, 'locale' ) );
        add_filter( 'woocommerce_checkout_fields', array( $this, 'fields' ), 20 );
    }
    public function locale( array $locale ): array {
        foreach ( array( 'state' => __( 'Tỉnh / Thành phố', 'vn-address-pro' ), 'city' => __( 'Phường / Xã / Đặc khu', 'vn-address-pro' ), 'address_1' => __( 'Số nhà, tên đường', 'vn-address-pro' ) ) as $field => $label ) {
            $locale['VN'][ $field ] = array_merge( $locale['VN'][ $field ] ?? array(), array( 'label' => $label, 'required' => true, 'hidden' => false ) );
        }
        $locale['VN']['state']['priority'] = 65;
        $locale['VN']['city']['priority'] = 70;
        $locale['VN']['address_1']['priority'] = 75;
        $locale['VN']['address_2']['priority'] = 80;
        return $locale;
    }
    public function fields( array $fields ): array {
        if ( ! Compatibility::request() ) { return $fields; }
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            if ( isset( $fields[ $prefix ] ) ) {
                $fields[ $prefix ][ AddressValidator::field( $prefix ) ] = array( 'type' => 'hidden', 'required' => false, 'priority' => 95 );
            }
        }
        return $fields;
    }
}
