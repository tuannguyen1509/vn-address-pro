<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce\Blocks;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\States;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\OrderAddress;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\CustomerAddress;
use Tunlamweb\VNAddressPro\Plugin;

defined( 'ABSPATH' ) || exit;

final class StoreApi {
    public const EXTENSION = 'vn-address-pro';
    private \WeakMap $raw_addresses;
    private array $validated_orders = array();
    public function __construct() { $this->raw_addresses = new \WeakMap(); }
    public function register(): void {
        add_filter( 'rest_pre_dispatch', array( $this, 'capture' ), 10, 3 );
        if ( did_action( 'woocommerce_blocks_loaded' ) ) { $this->schema(); }
        else { add_action( 'woocommerce_blocks_loaded', array( $this, 'schema' ) ); }
        add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'customer' ), 10, 2 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'order' ), 10, 2 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'registered_customer' ) );
    }
    public function capture( $response, $server, \WP_REST_Request $request ) {
        if ( $this->is_checkout( $request ) ) {
            $this->raw_addresses[ $request ] = array(
                'billing' => $request->get_param( 'billing_address' ),
                'shipping' => $request->get_param( 'shipping_address' ),
                'extensions' => $request->get_param( 'extensions' ),
            );
        }
        return $response;
    }
    private function is_checkout( \WP_REST_Request $request ): bool {
        return $request->get_method() === 'POST' && untrailingslashit( $request->get_route() ) === '/wc/store/v1/checkout';
    }
    public function schema(): void {
        woocommerce_store_api_register_endpoint_data( array(
            'endpoint' => CheckoutSchema::IDENTIFIER, 'namespace' => self::EXTENSION,
            'schema_callback' => static fn() => array(
                'billingWardCode' => array( 'type' => array( 'string','null' ), 'maxLength' => 12, 'context' => array( 'view','edit' ) ),
                'shippingWardCode' => array( 'type' => array( 'string','null' ), 'maxLength' => 12, 'context' => array( 'view','edit' ) ),
            ),
            'schema_type' => ARRAY_A,
        ) );
        woocommerce_store_api_register_endpoint_data( array(
            'endpoint' => CartSchema::IDENTIFIER, 'namespace' => self::EXTENSION,
            'schema_callback' => static fn() => array(
                'billing' => array( 'type' => 'object', 'readonly' => true, 'context' => array( 'view' ) ),
                'shipping' => array( 'type' => 'object', 'readonly' => true, 'context' => array( 'view' ) ),
            ),
            'data_callback' => array( $this, 'saved' ), 'schema_type' => ARRAY_A,
        ) );
    }
    public function saved(): array {
        $result = array();
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            $result[ $prefix ] = array( 'state' => '', 'wardCode' => '' );
            $customer = WC()->customer;
            if ( ! $customer || $customer->{"get_{$prefix}_country"}() !== 'VN' ) { continue; }
            $state = $customer->{"get_{$prefix}_state"}();
            $province = States::from_wc_state_code( $state );
            $ward = $customer->get_meta( AddressValidator::meta( $prefix, 'ward_code' ) );
            if ( $province !== null && ! is_wp_error( Plugin::instance()->address_service()->validate_selection( $province, $ward ) ) ) {
                $result[ $prefix ] = array( 'state' => $state, 'wardCode' => (string) $ward );
            }
        }
        return $result;
    }
    public function selections( \WP_REST_Request $request ): array {
        $extensions = $request->get_param( 'extensions' );
        $codes = is_array( $extensions ) && isset( $extensions[ self::EXTENSION ] ) && is_array( $extensions[ self::EXTENSION ] ) ? $extensions[ self::EXTENSION ] : array();
        $raw_extensions = $this->raw_addresses[ $request ]['extensions'] ?? null;
        $raw_codes = is_array( $raw_extensions ) && is_array( $raw_extensions[ self::EXTENSION ] ?? null ) ? $raw_extensions[ self::EXTENSION ] : $codes;
        if ( array_diff( array_keys( $raw_codes ), array( 'billingWardCode','shippingWardCode' ) ) ) {
            throw new RouteException( 'vnap_invalid_extension', __( 'Dữ liệu địa chỉ không hợp lệ.', 'vn-address-pro' ), 400 );
        }
        $selections = array();
        foreach ( array( 'billing','shipping' ) as $prefix ) {
            if ( $prefix === 'shipping' && ! WC()->cart->needs_shipping() ) { continue; }
            $address = $request->get_param( $prefix . '_address' );
            if ( ! is_array( $address ) ) { $address = array(); }
            // Store API accepts partial address updates; validate the effective country/state too.
            $address = array_merge( array(
                'country' => WC()->customer->{"get_{$prefix}_country"}(),
                'state' => WC()->customer->{"get_{$prefix}_state"}(),
            ), $address );
            // Woo's REST sanitizer maps state labels to keys. Preserve strict Phase 7 input semantics.
            $raw = $this->raw_addresses[ $request ][ $prefix ] ?? null;
            if ( is_array( $raw ) ) { $address = array_merge( $address, array_intersect_key( $raw, array_flip( array( 'state','country' ) ) ) ); }
            $selection = ( new AddressValidator() )->resolve( array(
                $prefix . '_country' => $address['country'] ?? '',
                $prefix . '_state' => $address['state'] ?? '',
                AddressValidator::field( $prefix ) => $codes[ $prefix . 'WardCode' ] ?? '',
            ), $prefix );
            if ( is_wp_error( $selection ) ) {
                throw new RouteException( 'vnap_' . $prefix . '_address', __( 'Vui lòng chọn Tỉnh / Thành phố và Phường / Xã / Đặc khu hợp lệ, cùng địa phương.', 'vn-address-pro' ), 400 );
            }
            $selections[ $prefix ] = $selection;
        }
        return $selections;
    }
    public function customer( \WC_Customer $customer, \WP_REST_Request $request ): void {
        if ( $this->is_checkout( $request ) ) { ( new CustomerAddress() )->write( $customer, $this->selections( $request ) ); }
    }
    public function order( \WC_Order $order, \WP_REST_Request $request ): void {
        if ( $this->is_checkout( $request ) ) {
            $selections = $this->selections( $request );
            ( new OrderAddress() )->write( $order, $selections );
            $this->validated_orders[ spl_object_id( $order ) ] = $selections;
        }
    }
    public function registered_customer( \WC_Order $order ): void {
        $key = spl_object_id( $order );
        if ( ! isset( $this->validated_orders[ $key ] ) || ! $order->get_customer_id() ) { return; }
        // Store API creates a new account AFTER the customer-update hook. Native Woo copies
        // core address properties from the order, but does not copy extension-owned metadata.
        $selections = $this->validated_orders[ $key ];
        unset( $this->validated_orders[ $key ] );
        $customer = new \WC_Customer( $order->get_customer_id() );
        $changed = false;
        foreach ( $selections as $prefix => $selection ) {
            foreach ( array( 'province','ward' ) as $part ) {
                $value = is_array( $selection ) ? (string) $selection[ $part ]['code'] : '';
                if ( (string) $customer->get_meta( AddressValidator::meta( $prefix, $part . '_code' ) ) !== $value ) { $changed = true; }
            }
        }
        if ( $changed ) { ( new CustomerAddress() )->write( $customer, $selections ); $customer->save(); }
    }
}
