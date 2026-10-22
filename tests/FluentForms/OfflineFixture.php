<?php
/** TEMPORARY MU fixture for isolated synthetic database only. Never deploy. */
defined( 'ABSPATH' ) || exit;
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
    update_option( 'vnap_test_fluent_http', (int) get_option( 'vnap_test_fluent_http', 0 ) + 1, false );
    if ( str_starts_with( $url, \Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider::UPSTREAM ) ) {
        update_option( 'vnap_test_fluent_provider_http', (int) get_option( 'vnap_test_fluent_provider_http', 0 ) + 1, false );
    }
    $urls = get_option( 'vnap_test_fluent_http_hosts', array() );
    $urls[] = wp_parse_url( $url, PHP_URL_HOST );
    update_option( 'vnap_test_fluent_http_hosts', array_slice( $urls, -20 ), false );
    return new WP_Error( 'offline', 'Synthetic offline test' );
}, PHP_INT_MAX, 3 );
add_filter( 'pre_wp_mail', static function( $return, $mail ) {
    $messages = get_option( 'vnap_test_fluent_mail', array() ); $messages[] = $mail['message'];
    update_option( 'vnap_test_fluent_mail', $messages, false ); return true;
}, PHP_INT_MAX, 2 );
add_filter( 'wpcf7_spam', '__return_false', PHP_INT_MAX );
