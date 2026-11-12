<?php
/** wp eval-file with WPForms inactive on a disposable site. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$adapter = new \Tunlamweb\VNAddressPro\Integrations\Forms\WPForms\WPFormsIntegration();
if ( $adapter->is_available() ) { throw new RuntimeException( 'Run with WPForms inactive' ); }
$adapter->boot();
if ( class_exists( '\Tunlamweb\VNAddressPro\Integrations\Forms\WPForms\AddressField', false ) ) { throw new RuntimeException( 'Vendor field class loaded without dependency' ); }
if ( is_wp_error( \Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_provinces() ) || ! str_contains( do_shortcode( '[vn_address_fields]' ), 'data-vnap-search' ) ) { throw new RuntimeException( 'Independent core/shortcode failed' ); }
WP_CLI::success( 'WPForms absent: gate, class isolation and independent core/search component passed.' );
