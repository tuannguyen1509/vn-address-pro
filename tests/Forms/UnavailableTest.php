<?php
/** Isolated disposable process: unavailable repository must fail closed, not fatal. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'WPCF7_VERSION' ) ) { return; }
require_once dirname( __DIR__ ) . '/Support/CacheState.php';
use Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7\{FormTags,Validator};
$metadata = get_option( 'vnap_dataset_metadata' ); $form = null;
$cache = new Tunlamweb\VNAddressPro\Tests\Support\CacheState( array( null, 79 ) );
$deny = static fn() => new WP_Error( 'offline', 'Offline test.' ); add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
try {
    update_option( 'vnap_dataset_metadata', array(), false );
    $cache->clear();
    $form = WPCF7_ContactForm::get_template(); $form->set_properties( array( 'form' => '[vnap_province* p][vnap_ward* w province:p]' ) );
    $html = $form->form_html(); $tags = FormTags::tags(); $tag = end( $tags );
    if ( ! str_contains( $html, 'temporarily unavailable' ) || ! is_wp_error( ( new Validator() )->selection( $tag, array( 'p' => '79', 'w' => '25747' ) ) ) ) { throw new RuntimeException( 'Unavailable data was accepted' ); }
    WP_CLI::success( 'Unavailable local/remote data renders controlled feedback and rejects submission without fatal.' );
} finally { update_option( 'vnap_dataset_metadata', $metadata, false ); $cache->restore(); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); }
