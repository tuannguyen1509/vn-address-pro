<?php
/** Disposable site only; run Woo Setup then Blocks Setup. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( ! defined( 'REST_REQUEST' ) ) { define( 'REST_REQUEST', true ); }
$original_rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;
$GLOBALS['wp']->query_vars['rest_route'] = '/wc/store/v1/checkout';
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Blocks\StoreApi;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Compatibility;
use Tunlamweb\VNAddressPro\Plugin;

$n = 0; $http = 0;
$check = static function ( $ok, $label ) use ( &$n ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$n; WP_CLI::line( 'PASS ' . $label ); };
$block = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); };
add_filter( 'pre_http_request', $block, PHP_INT_MAX );
set_error_handler( static function ( $severity, $message, $file, $line ) { if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false; } );
WC()->initialize_session(); WC()->initialize_cart();
$original_customer = WC()->customer;
$original_cart = WC()->cart;
WC()->customer = new WC_Customer(); WC()->cart = new WC_Cart();
WC()->cart->add_to_cart( (int) get_option( 'vnap_test_product_id' ) );
$customer_id = 0; $order = null;
try {
    $check( Compatibility::supports_blocks() && Compatibility::checkout_type() === 'block', 'Block capability and configured checkout detected' );
    $check( ! str_contains( get_post( wc_get_page_id( 'checkout' ) )->post_content, 'vn-address-pro/' ), 'Checkout page not rewritten with extension children' );
    foreach ( array( 'billing','shipping' ) as $type ) {
        $registered = WP_Block_Type_Registry::get_instance()->get_registered( 'vn-address-pro/' . $type . '-ward' );
        $check( $registered && $registered->parent === array( 'woocommerce/checkout-' . $type . '-address-block' ) && ! empty( $registered->attributes['lock']['default']['remove'] ), 'Registered forced ' . $type . ' address child' );
    }
    $locale = WC()->countries->get_country_locale();
    $check( $locale['VN']['city']['hidden'] && ! $locale['VN']['city']['required'] && empty( $locale['US']['city']['hidden'] ), 'VN-only city locale; international city retained' );
    $GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/users';
    $other_locale = apply_filters( 'woocommerce_get_country_locale', array( 'VN' => array() ) );
    $check( ! Compatibility::block_address_context() && ! $other_locale['VN']['city']['hidden'], 'Block city hiding does not leak into account or unrelated REST contexts' );
    $GLOBALS['wp']->query_vars['rest_route'] = '/wc/store/v1/checkout';
    $wards = Plugin::instance()->address_service()->get_wards( 79 );
    $ward = $wards['wards'][0];
    $api = new StoreApi();
    $data = array( 'billing_address' => array( 'country' => 'VN', 'state' => 'VN-79', 'city' => 'Forged' ), 'shipping_address' => array( 'country' => 'VN','state' => 'VN-79' ), 'extensions' => array( 'vn-address-pro' => array( 'billingWardCode' => (string) $ward['code'], 'shippingWardCode' => (string) $ward['code'] ) ) );
    $make = static function ( array $data ): WP_REST_Request { $request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' ); foreach ( $data as $key => $value ) { $request->set_param( $key, $value ); } return $request; };
    $request = $make( $data );
    $resolved = $api->selections( $request );
    $check( $resolved['billing']['ward']['name'] === $ward['name'] && $resolved['shipping']['ward']['code'] === $ward['code'], 'Both address selections resolved by core' );
    foreach ( array( 'missing','unknown','mismatch','label','array' ) as $kind ) {
        $bad = $data;
        if ( $kind === 'missing' ) { unset( $bad['extensions'] ); }
        elseif ( $kind === 'unknown' ) { $bad['extensions']['vn-address-pro']['billingWardCode'] = '99999999'; }
        elseif ( $kind === 'mismatch' ) { $bad['billing_address']['state'] = 'VN-1'; }
        elseif ( $kind === 'label' ) { $bad['billing_address']['state'] = 'Thành phố Hồ Chí Minh'; }
        else { $bad['extensions']['vn-address-pro']['billingWardCode'] = array( '1' ); }
        $rejected = false;
        try { $api->selections( $make( $bad ) ); } catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $error ) { $rejected = true; }
        $check( $rejected, 'Server rejects ' . $kind );
    }
    $raw = $data; $raw['billing_address']['state'] = 'Thành phố Hồ Chí Minh'; $request = $make( $raw );
    $api->capture( null, null, $request ); $request->set_param( 'billing_address', $data['billing_address'] );
    $rejected = false;
    try { $api->selections( $request ); } catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $error ) { $rejected = true; }
    $check( $rejected, 'Strict raw state survives Woo REST label sanitization' );
    $request = $make( $data );
    $customer_id = wc_create_new_customer( 'vnap-block-crud-' . wp_generate_uuid4() . '@example.invalid' );
    $customer = new WC_Customer( $customer_id ); $customer->set_billing_country( 'VN' ); $customer->set_shipping_country( 'VN' );
    $api->customer( $customer, $request ); $customer->save();
    $customer = new WC_Customer( $customer_id );
    $check( (int) $customer->get_meta( '_vnap_billing_province_code' ) === 79 && (int) $customer->get_meta( '_vnap_shipping_ward_code' ) === $ward['code'], 'Customer CRUD persists Phase 7 canonical keys' );
    $order = wc_create_order(); $order->set_billing_country( 'VN' ); $order->set_shipping_country( 'VN' );
    $api->order( $order, $request ); $order->save(); $loaded = wc_get_order( $order->get_id() );
    $check( (int) $loaded->get_meta( '_vnap_billing_province_code' ) === 79 && (int) $loaded->get_meta( '_vnap_shipping_ward_code' ) === $ward['code'], 'Order CRUD persists same canonical schema' );
    $check( $loaded->get_billing_city() === $ward['name'] && $loaded->get_shipping_state() === 'VN-79', 'Order city names and native state contract' );
    $check( str_contains( $loaded->get_formatted_billing_address(), $ward['name'] ) && ! str_contains( $loaded->get_formatted_billing_address(), 'VN-79' ), 'Native order formatting readable' );
    foreach ( array( 'billing','shipping' ) as $prefix ) { foreach ( array( 'province','ward' ) as $part ) { $customer->delete_meta_data( '_vnap_' . $prefix . '_' . $part . '_code' ); } }
    $customer->save(); $order->set_customer_id( $customer_id ); $api->registered_customer( $order );
    $customer = new WC_Customer( $customer_id );
    $check( (int) $customer->get_meta( '_vnap_billing_ward_code' ) === $ward['code'], 'Post-creation customer hook fills metadata missing from native account sync' );
    WC()->customer = $customer;
    $saved = $api->saved();
    $check( $saved['billing']['wardCode'] === (string) $ward['code'] && $saved['shipping']['state'] === 'VN-79', 'Cart extension exposes saved code and its matching province' );
    $foreign = $data; $foreign['billing_address'] = $foreign['shipping_address'] = array( 'country' => 'US','state' => 'CA','city' => 'Los Angeles' ); unset( $foreign['extensions'] );
    $check( $api->selections( $make( $foreign ) ) === array( 'billing' => null, 'shipping' => null ), 'Foreign addresses do not require VNAP codes' );
    $virtual = new WC_Product_Simple(); $virtual->set_virtual( true ); $virtual->set_price( 1 ); $virtual->save();
    WC()->cart = new WC_Cart(); WC()->cart->add_to_cart( $virtual->get_id() );
    try { $check( ! array_key_exists( 'shipping', $api->selections( $make( $data ) ) ), 'Virtual cart requires billing only' ); } finally { $virtual->delete( true ); }
    $check( $http === 0, 'Block validation and persistence use zero outgoing HTTP' );
    $page_id = wc_get_page_id( 'checkout' ); $original_content = get_post( $page_id )->post_content;
    try {
        wp_update_post( array( 'ID' => $page_id, 'post_content' => '[woocommerce_checkout]' ) );
        wp_enqueue_script( 'vnap-wc-classic', VNAP_URL . 'assets/js/woocommerce-classic.js' );
        do_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_before' );
        $check( Compatibility::checkout_type() === 'block' && ! wp_script_is( 'vnap-wc-classic', 'enqueued' ) && get_post( $page_id )->post_content === '[woocommerce_checkout]', 'Actual Woo Block enqueue context overrides page detection without rewriting content' );
    } finally { wp_update_post( array( 'ID' => $page_id, 'post_content' => $original_content ) ); }
    WP_CLI::success( $n . ' Block integration checks passed; WC ' . WC_VERSION . '; HPOS=' . get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) );
} finally {
    if ( $order ) { $order->delete( true ); }
    if ( $customer_id ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $customer_id ); }
    WC()->customer = $original_customer; WC()->cart = $original_cart;
    if ( $original_rest_route === null ) { unset( $GLOBALS['wp']->query_vars['rest_route'] ); }
    else { $GLOBALS['wp']->query_vars['rest_route'] = $original_rest_route; }
    remove_filter( 'pre_http_request', $block, PHP_INT_MAX ); restore_error_handler();
}
