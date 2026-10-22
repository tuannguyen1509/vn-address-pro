<?php
/** TEMPORARY MU plugin for disposable CF7 browser tests. Never install on production. */
defined( 'ABSPATH' ) || exit;
add_filter( 'pre_http_request', static fn() => new WP_Error( 'offline', 'Disposable test network blocked.' ), PHP_INT_MAX );
add_filter( 'wpcf7_spam', '__return_false', PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
// Report native composed mail within the same response, not a persistent submission log.
$vnap_test_mail = array();
add_filter( 'wpcf7_mail_components', static function ( $components ) use ( &$vnap_test_mail ) { $vnap_test_mail[] = $components['body']; return $components; } );
add_filter( 'wpcf7_submission_result', static function ( $result, $submission ) use ( &$vnap_test_mail ) {
    $result['vnap_test_mail'] = $vnap_test_mail;
    $result['vnap_test_codes'] = array_intersect_key( $submission->get_posted_data(), array_flip( array( 'home','home_ward','office','office_ward' ) ) );
    return $result;
}, 20, 2 );
