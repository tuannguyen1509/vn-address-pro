<?php
/** Disposable WordPress/WooCommerce CLI process with Classic checkout installed. */
defined( 'ABSPATH' ) || exit;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Assets;

$old_query = $GLOBALS['wp_query']; $queue = wp_styles()->queue; $count = 0;
$old_vars = $GLOBALS['wp']->query_vars; $checkout_context = static fn() => true;
$check = static function ( $ok, $message ) use ( &$count ) {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
    ++$count; echo 'PASS ' . $message . PHP_EOL;
};
$css_opt_in = static fn() => true; add_filter( 'vnap_checkout_css', $css_opt_in );
try {
    $assets = new Assets();
    foreach ( array( 'myaccount','cart','shop' ) as $page ) {
        $GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => wc_get_page_id( $page ) ) );
        wp_dequeue_style( 'vnap-wc-checkout' ); $assets->enqueue();
        $check( ! wp_style_is( 'vnap-wc-checkout', 'enqueued' ), 'Checkout CSS absent from ' . $page );
    }
    $GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => wc_get_page_id( 'checkout' ) ) );
    // Woo caches renderer detection before CLI creates a frontend query. Use its public context filter.
    add_filter( 'woocommerce_is_checkout', $checkout_context );
    $assets->enqueue();
    $check( wp_style_is( 'vnap-wc-checkout', 'enqueued' ), 'Classic checkout loads layout CSS' );
    $check( wp_styles()->registered['vnap-wc-checkout']->ver === VNAP_VERSION, 'Checkout CSS follows release version' );
    wp_dequeue_style( 'vnap-wc-checkout' );
    $GLOBALS['wp']->query_vars['order-received'] = 1;
    $assets->enqueue();
    $check( ! wp_style_is( 'vnap-wc-checkout', 'enqueued' ), 'Order confirmation excludes checkout CSS' );
    unset( $GLOBALS['wp']->query_vars['order-received'] );
    do_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_before' );
    $check( wp_style_is( 'vnap-wc-checkout', 'enqueued' ), 'Actual Block renderer loads checkout CSS' );
    echo 'SUCCESS ' . $count . ' checkout asset checks' . PHP_EOL;
} finally { remove_filter( 'vnap_checkout_css', $css_opt_in ); remove_filter( 'woocommerce_is_checkout', $checkout_context ); $GLOBALS['wp']->query_vars = $old_vars; $GLOBALS['wp_query'] = $old_query; wp_styles()->queue = $queue; }
