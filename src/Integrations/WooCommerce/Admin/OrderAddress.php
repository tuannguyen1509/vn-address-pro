<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce\Admin;

use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressEditor;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\OrderAddress as CanonicalOrderAddress;
defined( 'ABSPATH' ) || exit;

final class OrderAddress {
    private array $pending = array();
    public function register(): void {
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            add_filter( 'woocommerce_admin_' . $prefix . '_fields', function ( $fields, $order = false, $context = 'edit' ) use ( $prefix ) { return $this->fields( $fields, $order, $prefix, $context ); }, 20, 3 );
        }
        add_action( 'woocommerce_admin_order_data_after_order_details', static function ( $order ) { wp_nonce_field( 'vnap_order_address_' . $order->get_id(), 'vnap_order_address_nonce' ); } );
        // Native items/totals save at 10; Order Data sets address properties at 40.
        // Arm only immediately before Order Data, never on an earlier totals save.
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'prepare' ), 39 );
        add_action( 'woocommerce_before_order_object_save', array( $this, 'write' ), 20 );
        add_filter( 'woocommerce_ajax_get_customer_details', array( $this, 'customer_details' ), 20, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }
    public function assets(): void {
        if ( ! function_exists( 'wc_get_page_screen_id' ) ) { return; }
        $screen = get_current_screen();
        if ( ! $screen ) { return; }
        $action = $_GET['action'] ?? '';
        $legacy = $screen->id === 'shop_order' && $screen->base === 'post';
        $hpos = $screen->id === wc_get_page_screen_id( 'shop-order' ) && is_string( $action ) && in_array( $action, array( 'edit','new' ), true );
        if ( $legacy || $hpos ) { AddressEditor::enqueue( 'admin' ); }
    }
    public function fields( array $fields, $order, string $prefix, string $context = 'edit' ): array {
        if ( $context !== 'edit' || ! did_action( 'init' ) ) { return $fields; }
        // Woo 8.2 supplies only the fields argument; its output initializes $theorder.
        if ( ! $order instanceof \WC_Order ) { $order = $GLOBALS['theorder'] ?? false; }
        $code = $order instanceof \WC_Order ? $order->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) ) : '';
        $options = $order instanceof \WC_Order ? AddressEditor::options( $order->{"get_{$prefix}_country"}(), $order->{"get_{$prefix}_state"}(), $code, $prefix ) : array( '' => __( 'Chọn Phường / Xã / Đặc khu', 'vn-address-pro' ) );
        // Native load/copy address loops understand _billing_* / _shipping_* inputs.
        // The transport code is not an additional persisted metadata field.
        $fields['vnap_ward_code'] = array( 'type' => 'hidden', 'label' => '', 'show' => false, 'wrapper_class' => 'hidden', 'value' => is_scalar( $code ) ? (string) $code : '', 'custom_attributes' => array( 'data-vnap-initial-code' => is_scalar( $code ) ? (string) $code : '' ), 'update_callback' => static function () {} );
        $fields['vnap_ward'] = array( 'type' => 'select', 'id' => 'vnap_admin_' . $prefix . '_ward', 'label' => __( 'Phường / Xã / Đặc khu', 'vn-address-pro' ), 'show' => false, 'options' => $options, 'value' => is_scalar( $code ) ? (string) $code : '', 'update_callback' => static function () {} );
        return $fields;
    }
    public function prepare( $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! current_user_can( 'edit_shop_order', $order_id ) ) { return; }
        unset( $this->pending[ $order_id ] );
        $nonce = $_POST['vnap_order_address_nonce'] ?? '';
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            $data = array(); $changed = false; $present = false;
            foreach ( array( 'country','state','city' ) as $part ) {
                $key = '_' . $prefix . '_' . $part; $stored = $order->{"get_{$prefix}_{$part}"}( 'edit' );
                $value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $stored;
                $present = $present || isset( $_POST[ $key ] );
                $changed = $changed || $value !== $stored;
                $data[ $prefix . '_' . $part ] = $value;
            }
            $key = '_' . $prefix . '_vnap_ward_code';
            $stored = $order->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) );
            $stored = is_scalar( $stored ) ? (string) $stored : '';
            $code = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $stored;
            // Woo 8.2 has no field update_callback. Consume transport inputs before
            // its save loop, so it cannot persist parallel, noncanonical metadata.
            unset( $_POST[ $key ], $_POST[ 'vnap_admin_' . $prefix . '_ward' ] );
            $changed = $changed || $code !== $stored;
            if ( ! $present || ! $changed ) { continue; } // Keep historical/obsolete snapshots verbatim.
            $data[ AddressValidator::field( $prefix ) ] = $code;
            $selection = ( new AddressValidator() )->resolve( $data, $prefix );
            $invalid_shape = false;
            foreach ( $data as $value ) { if ( ! is_string( $value ) ) { $invalid_shape = true; } }
            if ( $invalid_shape || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'vnap_order_address_' . $order_id ) || is_wp_error( $selection ) ) {
                foreach ( array( 'country','state','city' ) as $part ) { $_POST[ '_' . $prefix . '_' . $part ] = wp_slash( $order->{"get_{$prefix}_{$part}"}( 'edit' ) ); }
                \WC_Admin_Meta_Boxes::add_error( __( 'Địa chỉ không hợp lệ. Tỉnh và phường/xã phải cùng địa phương; địa chỉ đã lưu được giữ nguyên.', 'vn-address-pro' ) );
                continue;
            }
            if ( is_array( $selection ) ) {
                $_POST[ '_' . $prefix . '_state' ] = wp_slash( $data[ $prefix . '_state' ] );
                $_POST[ '_' . $prefix . '_city' ] = wp_slash( $selection['ward']['name'] );
            }
            $this->pending[ $order_id ][ $prefix ] = $selection;
        }
    }
    public function write( \WC_Order $order ): void {
        if ( isset( $this->pending[ $order->get_id() ] ) ) {
            ( new CanonicalOrderAddress() )->write( $order, $this->pending[ $order->get_id() ] );
            unset( $this->pending[ $order->get_id() ] );
        }
    }
    public function customer_details( array $data, $customer, $user_id ): array {
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            $code = $customer->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) );
            $options = AddressEditor::options( $customer->{"get_{$prefix}_country"}(), $customer->{"get_{$prefix}_state"}(), $code, $prefix );
            $data[ $prefix ]['vnap_ward_code'] = is_scalar( $code ) && isset( $options[ (string) $code ] ) ? (string) $code : '';
        }
        return $data;
    }
}
