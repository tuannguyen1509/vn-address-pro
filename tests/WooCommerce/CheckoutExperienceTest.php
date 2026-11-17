<?php
/** Disposable database only. No mail or remote tax/invoice calls. */
defined( 'ABSPATH' ) || exit;
use Tunlamweb\VNAddressPro\Support\CheckoutPreferences;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\{VatFields,Assets};
$saved = get_option( CheckoutPreferences::OPTION, null ); $post = $_POST; $count = 0;
$checkout_page = get_post( wc_get_page_id( 'checkout' ) ); $checkout_content = $checkout_page->post_content;
$check = static function ( $ok, $label ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$count; echo 'PASS ' . $label . PHP_EOL; };
$order = null; $zone = null;
try {
    wp_update_post( array( 'ID' => $checkout_page->ID, 'post_content' => '[woocommerce_checkout]' ) );
    delete_option( CheckoutPreferences::OPTION );
    $check( ! CheckoutPreferences::get( 'css' ), 'CSS is opt-in by default' );
    wp_dequeue_style( 'vnap-wc-checkout' ); Assets::enqueue_layout();
    $check( ! wp_style_is( 'vnap-wc-checkout', 'enqueued' ), 'CSS off enqueues no checkout stylesheet' );
    CheckoutPreferences::save( array( 'css','compact','vat','unknown' ) ); Assets::enqueue_layout();
    $check( wp_style_is( 'vnap-wc-checkout', 'enqueued' ), 'Explicit CSS opt-in loads stylesheet' );
    $check( count( get_option( CheckoutPreferences::OPTION ) ) === 3, 'Only known preferences persisted' );
    $vat = new VatFields();
    $valid = array( 'requested' => '1', 'company' => 'Công ty thử nghiệm', 'tax_id' => 'TEST-123', 'address' => 'Địa chỉ hóa đơn thử', 'email' => 'invoice@example.invalid' );
    $check( is_array( $vat->normalize( $valid ) ), 'Complete VAT request accepted' );
    $check( $vat->normalize( array( 'requested' => '0', 'company' => array( 'forged' ) ) ) === array(), 'Opt-out ignores stale or forged VAT fields' );
    foreach ( array( 'company','tax_id','address' ) as $key ) { $bad = $valid; $bad[ $key ] = ''; $check( is_wp_error( $vat->normalize( $bad ) ), 'VAT selection requires ' . $key ); }
    foreach ( array( 'requested','company','tax_id','address','email' ) as $key ) { $bad = $valid; $bad[ $key ] = array( 'hostile' ); $check( is_wp_error( $vat->normalize( $bad ) ), 'Reject non-scalar VAT ' . $key ); }
    $bad = $valid; $bad['email'] = 'invalid-email'; $check( is_wp_error( $vat->normalize( $bad ) ), 'Invalid invoice email rejected' );
    $bad['email'] = ''; $check( is_array( $vat->normalize( $bad ) ), 'Invoice email optional' );
    $context = static fn() => true; add_filter( 'woocommerce_is_checkout', $context );
    $_POST = array(); foreach ( $valid as $key => $value ) { $_POST[ 'vnap_vat_' . $key ] = $value; }
    $order = wc_create_order(); $vat->save_classic( $order, array() ); $order->save();
    $check( $order->get_meta( '_vnap_vat_requested' ) === 'yes' && $order->get_meta( '_vnap_vat_tax_id' ) === 'TEST-123', 'Classic VAT saved in order-owned metadata' );
    $_POST['vnap_vat_requested'] = '0'; $vat->save_classic( $order, array() );
    $check( $order->get_meta( '_vnap_vat_requested' ) === '', 'Classic VAT opt-out clears only owned invoice metadata' );
    remove_filter( 'woocommerce_is_checkout', $context );
    $request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' ); $input = array();
    foreach ( $valid as $key => $value ) { $input[ 'vn-address-pro/vat-' . $key ] = $key === 'requested' ? true : $value; }
    $request->set_param( 'additional_fields', $input ); $vat->save_block( $order, $request );
    $check( $order->get_meta( '_vnap_vat_source' ) === 'block', 'Store API VAT normalizes to the same metadata' );
    $request->set_param( 'additional_fields', array( 'vn-address-pro/vat-requested' => false ) ); $vat->save_block( $order, $request );
    $check( $order->get_meta( '_vnap_vat_requested' ) === '', 'Store API opt-out clears draft invoice data' );
    $block = parse_blocks( '<!-- wp:woocommerce/checkout-fields-block --><div><!-- wp:woocommerce/checkout-payment-block /--><!-- wp:woocommerce/checkout-actions-block /--></div><!-- /wp:woocommerce/checkout-fields-block -->' )[0];
    $once = $vat->ensure_order_block( $block ); $twice = $vat->ensure_order_block( $once );
    $check( count( $once['innerBlocks'] ) === 3 && $once === $twice, 'Native VAT information block injected once before payment' );
    \Tunlamweb\VNAddressPro\Integrations\WooCommerce\Compatibility::use_block_context();
    $compact = new \Tunlamweb\VNAddressPro\Integrations\WooCommerce\CompactCheckout();
    $locales = array( 'VN' => array(), 'US' => array( 'postcode' => array( 'required' => true ) ) );
    $result = $compact->locale( $locales );
    $check( $result['US'] === $locales['US'], 'Compact mode preserves foreign postcode requirements' );
    $check( $result['VN']['postcode']['hidden'] && $result['VN']['address_2']['hidden'], 'Compact VN Block hides optional postal and apartment fields' );
    $zone = new WC_Shipping_Zone(); $zone->set_zone_name( 'Postal rule fixture' ); $zone->add_location( '700000', 'postcode' ); $zone->save();
    $result = $compact->locale( $locales );
    $check( ! isset( $result['VN']['postcode']['hidden'] ), 'Existing postcode shipping rules keep postcode accessible' );
    $check( $compact->different_address( true ) === false, 'Compact mode starts with a shared billing/shipping address' );
    echo 'SUCCESS ' . $count . ' checkout experience checks' . PHP_EOL;
} finally {
    wp_update_post( array( 'ID' => $checkout_page->ID, 'post_content' => $checkout_content ) );
    if ( $order ) { $order->delete( true ); }
    if ( $zone ) { $zone->delete(); }
    $_POST = $post; if ( isset( $context ) ) { remove_filter( 'woocommerce_is_checkout', $context ); }
    if ( $saved === null ) { delete_option( CheckoutPreferences::OPTION ); } else { update_option( CheckoutPreferences::OPTION, $saved, false ); }
}
