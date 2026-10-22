<?php
/** Disposable database only: install a standard Block checkout without extension children. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$names = array( 'express-payment','contact-information','shipping-address','billing-address','shipping-methods','payment','terms','actions' );
$content = '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"><!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block">';
foreach ( $names as $name ) { $content .= '<!-- wp:woocommerce/checkout-' . $name . '-block --><div class="wp-block-woocommerce-checkout-' . $name . '-block"></div><!-- /wp:woocommerce/checkout-' . $name . '-block -->'; }
$content .= '</div><!-- /wp:woocommerce/checkout-fields-block --><!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block"><!-- wp:woocommerce/checkout-order-summary-block --><div class="wp-block-woocommerce-checkout-order-summary-block"></div><!-- /wp:woocommerce/checkout-order-summary-block --></div><!-- /wp:woocommerce/checkout-totals-block --></div><!-- /wp:woocommerce/checkout -->';
wp_update_post( array( 'ID' => wc_get_page_id( 'checkout' ), 'post_content' => $content ) );
$virtual = wc_get_product( (int) get_option( 'vnap_test_virtual_product_id' ) ) ?: new WC_Product_Simple();
$virtual->set_name( 'VNAP virtual checkout test product' ); $virtual->set_virtual( true ); $virtual->set_regular_price( 100000 ); $virtual->set_status( 'publish' ); $virtual->save();
update_option( 'vnap_test_virtual_product_id', $virtual->get_id(), false );
WP_CLI::success( 'Block checkout installed without VNAP child markup; automatic registration must supply fields.' );
