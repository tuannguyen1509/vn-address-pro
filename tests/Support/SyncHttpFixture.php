<?php
/** Test MU fixture ONLY. Copy to a disposable site's mu-plugins, then remove. */
defined( 'ABSPATH' ) || exit;
// Isolate this offline plugin test from unrelated WordPress.org admin update checks.
add_action( 'init', static function () {
    foreach ( array( '_maybe_update_core', '_maybe_update_plugins', '_maybe_update_themes' ) as $callback ) { remove_action( 'admin_init', $callback ); }
    remove_action( 'load-plugins.php', 'wp_update_plugins' );
} );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
    update_option( 'vnap_test_http_count', (int) get_option( 'vnap_test_http_count', 0 ) + 1, false );
    if ( $url === 'https://provinces.open-api.vn/api/v2/?depth=2' ) {
        $data = json_decode( file_get_contents( WP_PLUGIN_DIR . '/vn-address-pro/data/vn-address.json' ), true );
        return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $data['provinces'] ) );
    }
    return new WP_Error( 'vnap_test_http_blocked', 'Test environment blocks outgoing HTTP.' );
}, PHP_INT_MAX, 3 );
