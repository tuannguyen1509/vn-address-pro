<?php
/** Disposable-site setup ONLY. Overwrites WooCommerce test configuration. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
update_option( 'woocommerce_store_address', 'Test street' ); update_option( 'woocommerce_default_country', 'VN:VN-79' );
update_option( 'woocommerce_currency', 'VND' ); update_option( 'woocommerce_allowed_countries', 'all' ); update_option( 'woocommerce_ship_to_countries', '' );
update_option( 'woocommerce_default_customer_address', 'base' ); update_option( 'woocommerce_ship_to_destination', 'shipping' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' ); update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
update_option( 'woocommerce_registration_generate_username', 'yes' ); update_option( 'woocommerce_registration_generate_password', 'yes' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ) );
update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Bank transfer' ) );
update_option( 'woocommerce_coming_soon', 'no' ); update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_tax_based_on', 'shipping' ); update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_checkout_terms_and_conditions_checkbox_text', '' ); update_option( 'woocommerce_terms_page_id', 0 );
foreach ( array( 'checkout' => '[woocommerce_checkout]', 'cart' => '[woocommerce_cart]', 'myaccount' => '[woocommerce_my_account]' ) as $name => $content ) {
    $id = (int) get_option( 'woocommerce_' . $name . '_page_id' );
    $post = array( 'post_title' => ucfirst( $name ), 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content );
    if ( $id > 0 && get_post( $id ) ) { $post['ID'] = $id; wp_update_post( $post ); } else { $id = wp_insert_post( $post ); }
    update_option( 'woocommerce_' . $name . '_page_id', $id );
}
$product = new WC_Product_Simple(); $product->set_name( 'VNAP checkout test product' ); $product->set_status( 'publish' ); $product->set_regular_price( '100000' ); $product->set_virtual( false ); $product->set_weight( '1' ); $id = $product->save();
update_option( 'vnap_test_product_id', $id, false );
foreach ( WC_Shipping_Zones::get_zones() as $zone ) { WC_Shipping_Zones::delete_zone( $zone['zone_id'] ); }
foreach ( array( array( 'Province zone', 'state', 'VN:VN-79', '15000', 0 ), array( 'VN zone', 'country', 'VN', '30000', 1 ), array( 'US zone', 'country', 'US', '40000', 2 ) ) as $definition ) {
    [ $name,$type,$code,$cost,$order ] = $definition;
    $zone = new WC_Shipping_Zone(); $zone->set_zone_name( $name ); $zone->set_zone_order( $order ); $zone->add_location( $code, $type ); $zone->save();
    $instance = $zone->add_shipping_method( 'flat_rate' ); update_option( 'woocommerce_flat_rate_' . $instance . '_settings', array( 'title' => $name, 'cost' => $cost, 'tax_status' => 'taxable' ) );
}
$tax_id = WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'VN', 'tax_rate_state' => 'VN-79', 'tax_rate' => '10.0000', 'tax_rate_name' => 'VNAP Test Tax', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => '', 'tax_rate_compound' => 0 ) );
WC_Cache_Helper::invalidate_cache_group( 'taxes' );
WC_Install::create_tables(); flush_rewrite_rules();
WP_CLI::line( wp_json_encode( array( 'product' => $id, 'checkout' => wc_get_checkout_url(), 'version' => WC_VERSION ) ) );
