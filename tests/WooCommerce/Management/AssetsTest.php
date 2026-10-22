<?php
/** Isolated CLI process; no persistent state is changed. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) { return; }
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php'; require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
$old_screen = $GLOBALS['current_screen'] ?? null; $old_get = $_GET; $queue = wp_scripts()->queue;
try {
    $admin = new \Tunlamweb\VNAddressPro\Integrations\WooCommerce\Admin\OrderAddress();
    foreach ( array( 'dashboard','edit-shop_order','woocommerce_page_wc-orders' ) as $id ) {
        $_GET = array(); set_current_screen( $id ); wp_dequeue_script( 'vnap-address-management' ); $admin->assets();
        if ( wp_script_is( 'vnap-address-management', 'enqueued' ) ) { throw new RuntimeException( 'Management asset leaked onto ' . $id ); }
        WP_CLI::line( 'PASS Management assets absent from ' . $id );
    }
    $_GET = array( 'action' => 'edit' ); set_current_screen( wc_get_page_screen_id( 'shop-order' ) ); $admin->assets();
    if ( ! wp_script_is( 'vnap-address-management', 'enqueued' ) ) { throw new RuntimeException( 'Order edit assets missing' ); }
    WP_CLI::success( '4 admin asset isolation checks passed.' );
} finally { $GLOBALS['current_screen'] = $old_screen; $_GET = $old_get; wp_scripts()->queue = $queue; }
