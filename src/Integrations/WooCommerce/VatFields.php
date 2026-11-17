<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
use Tunlamweb\VNAddressPro\Support\CheckoutPreferences;
defined( 'ABSPATH' ) || exit;

/** Collect invoice requests only. No tax calculation or external invoice service. */
final class VatFields {
    public static function fields(): array {
        return array( 'company' => __( 'Tên đơn vị xuất hóa đơn', 'vn-address-pro' ), 'tax_id' => __( 'Mã số thuế', 'vn-address-pro' ), 'address' => __( 'Địa chỉ xuất hóa đơn', 'vn-address-pro' ), 'email' => __( 'Email nhận hóa đơn', 'vn-address-pro' ) );
    }
    public function register(): void {
        add_action( 'woocommerce_after_order_notes', array( $this, 'render' ) );
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic' ), 25, 2 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_classic' ), 25, 2 );
        add_action( 'woocommerce_init', array( $this, 'register_block_fields' ) );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_block' ), 25, 2 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin' ) );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'admin' ) );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'email' ), 20, 4 );
        add_filter( 'render_block_data', array( $this, 'ensure_order_block' ) );
    }
    public static function block_supported(): bool {
        return function_exists( 'woocommerce_register_additional_checkout_field' ) && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.9.0', '>=' );
    }
    private function posted(): array {
        $result = array();
        foreach ( array_merge( array( 'requested' ), array_keys( self::fields() ) ) as $key ) {
            $value = $_POST[ 'vnap_vat_' . $key ] ?? '';
            $result[ $key ] = is_string( $value ) ? wp_unslash( $value ) : $value;
        }
        return $result;
    }
    public function normalize( array $raw ): array|\WP_Error {
        $flag = $raw['requested'] ?? false;
        if ( ! in_array( $flag, array( true, false, '1', '0', 1, 0, '', null ), true ) ) { return new \WP_Error( 'vnap_vat_invalid', __( 'Yêu cầu VAT không hợp lệ.', 'vn-address-pro' ) ); }
        if ( ! in_array( $flag, array( true, '1', 1 ), true ) ) { return array(); }
        $result = array();
        foreach ( self::fields() as $key => $label ) {
            $value = $raw[ $key ] ?? '';
            if ( ! is_string( $value ) || strlen( $value ) > ( $key === 'address' ? 1200 : 300 ) ) { return new \WP_Error( 'vnap_vat_invalid', __( 'Thông tin VAT không hợp lệ hoặc quá dài.', 'vn-address-pro' ) ); }
            $value = sanitize_text_field( $value );
            if ( $key !== 'email' && $value === '' ) { return new \WP_Error( 'vnap_vat_required', sprintf( __( 'Vui lòng điền %s để yêu cầu xuất hóa đơn VAT.', 'vn-address-pro' ), $label ) ); }
            if ( $key === 'email' && $value !== '' && ! is_email( $value ) ) { return new \WP_Error( 'vnap_vat_email', __( 'Email nhận hóa đơn chưa hợp lệ.', 'vn-address-pro' ) ); }
            $result[ $key ] = $value;
        }
        return $result;
    }
    public function render( $checkout ): void {
        if ( ! CheckoutPreferences::get( 'vat' ) || ! Compatibility::request() ) { return; }
        $posted = $this->posted(); $requested = in_array( $posted['requested'], array( '1', 1, true ), true );
        echo '<section class="vnap-vat"><h3>' . esc_html__( 'Hóa đơn VAT (nếu cần)', 'vn-address-pro' ) . '</h3>';
        woocommerce_form_field( 'vnap_vat_requested', array( 'type' => 'checkbox', 'label' => __( 'Thêm thông tin VAT', 'vn-address-pro' ), 'class' => array( 'form-row-wide' ), 'custom_attributes' => array( 'aria-controls' => 'vnap-vat-fields', 'aria-expanded' => $requested ? 'true' : 'false' ) ), $requested );
        echo '<fieldset id="vnap-vat-fields" data-vnap-vat-fields' . ( $requested ? '' : ' hidden style="display:none"' ) . '><legend>' . esc_html__( 'Thông tin xuất hóa đơn', 'vn-address-pro' ) . '</legend>';
        foreach ( self::fields() as $key => $label ) {
            woocommerce_form_field( 'vnap_vat_' . $key, array( 'type' => $key === 'email' ? 'email' : 'text', 'label' => $label, 'required' => $key !== 'email', 'class' => array( 'form-row-wide' ), 'maxlength' => $key === 'address' ? 400 : 100, 'custom_attributes' => array( 'data-vnap-vat-required' => $key === 'email' ? '0' : '1' ) ), is_string( $posted[ $key ] ) ? $posted[ $key ] : '' );
        }
        echo '</fieldset><noscript><p>' . esc_html__( 'Vui lòng bật JavaScript để thêm thông tin VAT. Bạn vẫn có thể đặt hàng cơ bản.', 'vn-address-pro' ) . '</p></noscript></section>';
    }
    public function validate_classic( array $data, \WP_Error $errors ): void {
        if ( ! CheckoutPreferences::get( 'vat' ) || ! Compatibility::request() ) { return; }
        $result = $this->normalize( $this->posted() );
        if ( is_wp_error( $result ) ) { $errors->add( $result->get_error_code(), $result->get_error_message() ); }
    }
    private function write( \WC_Order $order, array $result, string $source ): void {
        foreach ( array_merge( array( 'requested','source' ), array_keys( self::fields() ) ) as $key ) { $order->delete_meta_data( '_vnap_vat_' . $key ); }
        if ( ! $result ) { return; }
        $order->update_meta_data( '_vnap_vat_requested', 'yes' ); $order->update_meta_data( '_vnap_vat_source', $source );
        foreach ( $result as $key => $value ) { $order->update_meta_data( '_vnap_vat_' . $key, $value ); }
    }
    public function save_classic( \WC_Order $order, array $data ): void {
        if ( ! CheckoutPreferences::get( 'vat' ) || ! Compatibility::request() ) { return; }
        $result = $this->normalize( $this->posted() ); if ( ! is_wp_error( $result ) ) { $this->write( $order, $result, 'classic' ); }
    }
    public function register_block_fields(): void {
        if ( ! CheckoutPreferences::get( 'vat' ) || ! self::block_supported() ) { return; }
        woocommerce_register_additional_checkout_field( array( 'id' => 'vn-address-pro/vat-requested', 'label' => __( 'Thêm thông tin VAT', 'vn-address-pro' ), 'location' => 'order', 'type' => 'checkbox' ) );
        $yes = array( 'checkout' => array( 'properties' => array( 'additional_fields' => array( 'properties' => array( 'vn-address-pro/vat-requested' => array( 'const' => true ) ), 'required' => array( 'vn-address-pro/vat-requested' ) ) ), 'required' => array( 'additional_fields' ) ) );
        $hidden = array( 'checkout' => array( 'properties' => array( 'additional_fields' => array( 'properties' => array( 'vn-address-pro/vat-requested' => array( 'not' => array( 'const' => true ) ) ) ) ) ) );
        foreach ( self::fields() as $key => $label ) {
            woocommerce_register_additional_checkout_field( array(
                'id' => 'vn-address-pro/vat-' . $key, 'label' => $label, 'location' => 'order', 'type' => 'text',
                'required' => $key === 'email' ? false : $yes, 'hidden' => $hidden,
                'attributes' => array( 'maxLength' => $key === 'address' ? 400 : 100 ),
                'sanitize_callback' => static fn( $value ) => sanitize_text_field( $value ),
                'validate_callback' => static function ( $value ) use ( $key ) {
                    return $key === 'email' && $value !== '' && ! is_email( $value ) ? new \WP_Error( 'vnap_vat_email', __( 'Email nhận hóa đơn chưa hợp lệ.', 'vn-address-pro' ) ) : null;
                },
            ) );
        }
    }
    public function save_block( \WC_Order $order, \WP_REST_Request $request ): void {
        if ( ! CheckoutPreferences::get( 'vat' ) || ! self::block_supported() ) { return; }
        $input = $request->get_param( 'additional_fields' ); $input = is_array( $input ) ? $input : array(); $raw = array();
        foreach ( array_merge( array( 'requested' ), array_keys( self::fields() ) ) as $key ) { $raw[ $key ] = $input[ 'vn-address-pro/vat-' . $key ] ?? ''; }
        $result = $this->normalize( $raw );
        if ( is_wp_error( $result ) ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( $result->get_error_code(), $result->get_error_message(), 400 ); }
        $this->write( $order, $result, 'block' );
    }
    public function ensure_order_block( array $block ): array {
        if ( ! CheckoutPreferences::get( 'vat' ) || ! self::block_supported() || ( $block['blockName'] ?? '' ) !== 'woocommerce/checkout-fields-block' || is_admin() ) { return $block; }
        foreach ( $block['innerBlocks'] ?? array() as $child ) { if ( ( $child['blockName'] ?? '' ) === 'woocommerce/checkout-additional-information-block' ) { return $block; } }
        $child = parse_blocks( '<!-- wp:woocommerce/checkout-additional-information-block /-->' )[0];
        $children = $block['innerBlocks'] ?? array(); $index = count( $children );
        foreach ( $children as $i => $item ) { if ( in_array( $item['blockName'] ?? '', array( 'woocommerce/checkout-payment-block','woocommerce/checkout-terms-block','woocommerce/checkout-actions-block' ), true ) ) { $index = $i; break; } }
        array_splice( $children, $index, 0, array( $child ) ); $block['innerBlocks'] = $children;
        $content = $block['innerContent'] ?? array(); $seen = 0; $position = max( 0, count( $content ) - 1 );
        foreach ( $content as $i => $part ) { if ( $part === null && $seen++ === $index ) { $position = $i; break; } }
        array_splice( $content, $position, 0, array( null ) ); $block['innerContent'] = $content;
        return $block;
    }
    public function admin( \WC_Order $order ): void { $this->display( $order, false ); }
    public function email( \WC_Order $order, $sent_to_admin, $plain_text, $email ): void { $this->display( $order, (bool) $plain_text ); }
    private function display( \WC_Order $order, bool $plain ): void {
        if ( $order->get_meta( '_vnap_vat_requested' ) !== 'yes' || $order->get_meta( '_vnap_vat_source' ) !== 'classic' ) { return; }
        echo $plain ? "\nVAT\n" : '<h3>VAT</h3><dl class="vnap-vat-summary">';
        foreach ( self::fields() as $key => $label ) {
            $value = (string) $order->get_meta( '_vnap_vat_' . $key );
            echo $plain ? wp_strip_all_tags( $label . ': ' . $value ) . "\n" : '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
        }
        if ( ! $plain ) { echo '</dl>'; }
    }
}
