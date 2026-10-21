<?php
/** Test MU plugin: copy to a disposable site's mu-plugins before offline activation. */
defined( 'ABSPATH' ) || exit;
add_filter( 'pre_http_request', static function () {
    update_option( 'vnap_test_http_count', (int) get_option( 'vnap_test_http_count', 0 ) + 1, false );
    return new WP_Error( 'vnap_test_offline', 'Network disabled for offline installation test.' );
}, PHP_INT_MAX );
