<?php
/** Disposable database only. Run Setup.php first; test both HPOS and legacy stores separately. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\{States,AddressValidator,ClassicCheckout,OrderAddress,CustomerAddress,Compatibility,WooCommerceIntegration};
use Tunlamweb\VNAddressPro\Plugin;

$n = 0; $http = 0;
$assert = static function ( $ok, $label ) use ( &$n ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$n; WP_CLI::line( 'PASS ' . $label ); };
$deny = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX ); add_filter( 'pre_wp_mail', '__return_true' );
set_error_handler( static function ( $severity, $message, $file, $line ) { if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false; } );
$orders = array(); $customer_id = 0;
try {
    $assert( Compatibility::supported() && Compatibility::checkout_type() === 'classic', 'Conditional module supports this Woo and Classic page' );
    $features = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin( VNAP_BASENAME );
    $assert( in_array( 'custom_order_tables', $features['compatible'], true ), 'HPOS compatibility declared with actual plugin entrypoint' );
    $assert( in_array( 'cart_checkout_blocks', $features[ Compatibility::supports_blocks() ? 'compatible' : 'incompatible' ], true ), 'Checkout Blocks declaration matches supported public API capability' );
    $before = has_filter( 'woocommerce_states' ); WooCommerceIntegration::load(); $assert( has_filter( 'woocommerce_states' ) === $before, 'Integration bootstrap idempotent' );
    $provinces = Plugin::instance()->address_service()->get_provinces(); $states = WC()->countries->get_states( 'VN' );
    $assert( count( $states ) === count( $provinces ) && isset( $states['VN-79'] ), 'States come from current core dataset' );
    $assert( States::to_wc_state_code( 79 ) === 'VN-79' && States::from_wc_state_code( 'VN-79' ) === 79, 'Stable reversible state mapping' );
    foreach ( array( '79','VN-079','vn-79','VN-79abc','VN--79','VN-0','VN-' . str_repeat( '9', 30 ) ) as $bad ) { $assert( States::from_wc_state_code( $bad ) === null, 'Strict state parse rejects ' . $bad ); }
    $assert( isset( WC()->countries->get_states( 'US' )['CA'] ), 'International state list preserved' );
    $locale = WC()->countries->get_country_locale();
    $assert( $locale['VN']['state']['required'] && ! $locale['VN']['state']['hidden'] && ! $locale['VN']['postcode']['required'], 'VN locale enables province and retains optional postcode' );
    $fields = WC()->checkout()->get_checkout_fields();
    $assert( isset( $fields['billing']['billing_address_2'], $fields['billing']['billing_phone'], $fields['billing']['billing_email'], $fields['billing']['vnap_billing_ward_code'], $fields['shipping']['vnap_shipping_ward_code'] ), 'Core fields retained plus canonical hidden fields' );
    $a = Plugin::instance()->address_service()->get_wards( 79 ); $b = Plugin::instance()->address_service()->get_wards( 1 );
    $ward = $a['wards'][0]['code']; $other = $b['wards'][0]['code'];
    $data = array( 'billing_country' => 'VN', 'billing_state' => 'VN-79', 'billing_city' => 'FORGED NAME', 'billing_first_name' => 'Test', 'billing_last_name' => 'Customer', 'billing_address_1' => 'User-entered street', 'billing_postcode' => '', 'billing_email' => 'checkout-test@example.invalid', 'billing_phone' => '0901234567', 'vnap_billing_ward_code' => (string) $ward, 'shipping_country' => 'VN', 'shipping_state' => 'VN-1', 'shipping_city' => 'FORGED SHIPPING', 'vnap_shipping_ward_code' => (string) $other, 'ship_to_different_address' => true );
    $validator = new AddressValidator();
    $assert( is_array( $validator->resolve( $data, 'billing' ) ), 'Valid billing relationship resolved by core' );
    foreach ( array( array( 'billing_state','' ),array( 'vnap_billing_ward_code','' ),array( 'billing_state','VN-999999' ),array( 'vnap_billing_ward_code','999999' ),array( 'vnap_billing_ward_code',(string) $other ),array( 'billing_state','79' ),array( 'billing_state', $states['VN-79'] ),array( 'vnap_billing_ward_code',array( $ward ) ) ) as [ $key,$value ] ) {
        $bad = $data; $bad[ $key ] = $value; $assert( is_wp_error( $validator->resolve( $bad, 'billing' ) ), 'Reject tampered/missing ' . $key );
    }
    $foreign = $data; $foreign['billing_country'] = 'US'; $foreign['billing_state'] = 'CA'; $foreign['billing_city'] = 'Los Angeles';
    $assert( $validator->resolve( $foreign, 'billing' ) === null, 'Non-VN bypasses VN relationship validation' );
    $checkout = new ClassicCheckout(); $clean = $checkout->normalize( $data );
    $hostile = $data; $hostile['billing_state'] = array( 'VN-79' ); $hostile['billing_city'] = array( 'fake' );
    $safe = $checkout->normalize( $hostile ); $hostile_errors = new WP_Error(); $checkout->validate( $safe, $hostile_errors );
    $assert( $safe['billing_state'] === '' && $safe['billing_city'] === '' && $hostile_errors->has_errors(), 'Hostile scalar-field arrays rejected without entering Woo validators' );
    $clean = $checkout->normalize( $data );
    $assert( $clean['billing_city'] === $a['wards'][0]['name'] && $clean['shipping_city'] === $b['wards'][0]['name'] && $clean['billing_address_1'] === $data['billing_address_1'], 'Billing/shipping names canonical; street unchanged' );
    $errors = new WP_Error(); $checkout->validate( $clean, $errors ); $assert( ! $errors->has_errors(), 'Valid checkout passes server hook' );
    $bad = $data; $bad['vnap_shipping_ward_code'] = (string) $ward; $checkout->normalize( $bad ); $errors = new WP_Error(); $checkout->validate( $bad, $errors );
    $assert( $errors->get_error_code() === 'vnap_shipping_address', 'Shipping independently rejects wrong parent' );
    $bad = $data; $bad['billing_state'] = $states['VN-79']; $checkout->normalize( $bad ); $errors = new WP_Error(); $checkout->validate( $data, $errors );
    $assert( $errors->has_errors(), 'Raw state name remains rejected even if Woo later maps it' );
    $checkout->normalize( $foreign ); $errors = new WP_Error(); $checkout->validate( $foreign, $errors ); $assert( ! $errors->has_errors(), 'Foreign billing native value preserved' );
    $clean = $checkout->normalize( $data );
    $order = new WC_Order(); $order->set_billing_country( 'VN' ); $order->set_shipping_country( 'VN' ); $checkout->order( $order, $clean ); $id = $order->save(); $orders[] = $id; $loaded = wc_get_order( $id );
    $assert( (int) $loaded->get_meta( '_vnap_billing_province_code' ) === 79 && (int) $loaded->get_meta( '_vnap_billing_ward_code' ) === $ward, 'Order CRUD persists billing canonical codes' );
    $assert( (int) $loaded->get_meta( '_vnap_shipping_province_code' ) === 1 && (int) $loaded->get_meta( '_vnap_shipping_ward_code' ) === $other, 'Order CRUD persists independent shipping codes' );
    $assert( $loaded->get_billing_city() === $a['wards'][0]['name'] && $loaded->get_billing_state() === 'VN-79', 'Woo native order fields remain human-readable city and stable state' );
    $loaded->update_meta_data( '_vnap_billing_province_name', 'Historical province snapshot' ); $loaded->save();
    $assert( str_contains( $loaded->get_formatted_billing_address(), 'Historical province snapshot' ) && ! str_contains( $loaded->get_formatted_billing_address(), 'VN-79' ), 'Historical province snapshot survives current dataset labels' );
    $emails = WC()->mailer()->get_emails(); $email = $emails['WC_Email_Customer_Processing_Order']; $email->object = $loaded;
    $email_html = wc_get_template_html( 'emails/email-addresses.php', array( 'order' => $loaded, 'email' => $email, 'sent_to_admin' => false, 'plain_text' => false ) );
    $assert( str_contains( $email_html, 'Historical province snapshot' ) && ! str_contains( $email_html, 'VN-79' ), 'Native Woo email address block uses readable historical snapshot' );
    $old_order = new WC_Order(); $old_order->set_billing_country( 'VN' ); $old_order->set_billing_state( 'Legacy province' ); $old_order->set_billing_city( 'Legacy city' );
    $assert( str_contains( $old_order->get_formatted_billing_address(), 'Legacy city' ), 'Old order without VNAP meta displays safely' );
    $customer_id = wc_create_new_customer( 'vnap-crud-' . wp_generate_uuid4() . '@example.invalid', '', wp_generate_password() );
    if ( is_wp_error( $customer_id ) ) { throw new RuntimeException( 'Test customer creation failed' ); }
    $customer = new WC_Customer( $customer_id ); $checkout->customer( $customer, $clean ); $customer->save(); $customer = new WC_Customer( $customer_id );
    $assert( (int) $customer->get_meta( '_vnap_billing_ward_code' ) === $ward && (int) $customer->get_meta( '_vnap_shipping_ward_code' ) === $other, 'Customer CRUD saves canonical addresses' );
    $snapshot = $loaded->get_billing_city(); $newdata = $data; $newdata['billing_state'] = 'VN-1'; $newdata['vnap_billing_ward_code'] = (string) $other;
    $cleannew = $checkout->normalize( $newdata ); $checkout->customer( $customer, $cleannew ); $customer->save();
    $assert( wc_get_order( $id )->get_billing_city() === $snapshot, 'Customer edits never rewrite historical order' );
    $cleanforeign = $checkout->normalize( $foreign ); $checkout->customer( $customer, $cleanforeign ); $customer->save();
    $assert( $customer->get_meta( '_vnap_billing_ward_code' ) === '', 'Foreign checkout removes obsolete customer VN codes' );
    $same = $data; $same['ship_to_different_address'] = false; $same['shipping_country'] = 'VN'; $same['shipping_state'] = 'VN-79';
    $clean = $checkout->normalize( $same ); $assert( $clean['vnap_shipping_ward_code'] === (string) $ward && $clean['shipping_city'] === $a['wards'][0]['name'], 'Same-as-billing shipping copies canonical ward safely' );
    $zone79 = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => array( 'country' => 'VN','state' => 'VN-79','postcode' => '' ) ) );
    $zone1 = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => array( 'country' => 'VN','state' => 'VN-1','postcode' => '' ) ) );
    $assert( $zone79->get_zone_name() === 'Province zone' && $zone1->get_zone_name() === 'VN zone', 'State-specific and country-only shipping zones match' );
    $tax = WC_Tax::find_rates( array( 'country' => 'VN','state' => 'VN-79','postcode' => '', 'city' => '', 'tax_class' => '' ) );
    $assert( ! empty( $tax ), 'Province state identifier resolves tax rates' );
    $assert( $http === 0, 'All address integration reads work offline with zero HTTP' );
    $page_id = wc_get_page_id( 'checkout' ); $original_content = get_post( $page_id )->post_content;
    try {
        wp_update_post( array( 'ID' => $page_id, 'post_content' => '<!-- wp:woocommerce/checkout /-->' ) );
        $assert( Compatibility::checkout_type() === 'block' && ! Compatibility::request(), 'Checkout Block detected; Classic request path disabled' );
        $untouched = ( new ClassicCheckout() )->normalize( $data );
        $assert( $untouched === $data, 'Classic normalization does not modify Block context' );
    } finally { wp_update_post( array( 'ID' => $page_id, 'post_content' => $original_content ) ); }
    WP_CLI::success( $n . ' Woo integration checks passed; WC ' . WC_VERSION . '; HPOS=' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'yes' : 'no' ) );
} finally {
    restore_error_handler(); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', '__return_true' );
    foreach ( $orders as $id ) { $order = wc_get_order( $id ); if ( $order ) { $order->delete( true ); } }
    if ( is_int( $customer_id ) && $customer_id > 0 ) { $customer = new WC_Customer( $customer_id ); $customer->delete( true ); }
}
