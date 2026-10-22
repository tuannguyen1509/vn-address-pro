<?php
/** Run with Fluent inactive, or with a pre-bootstrap FLUENTFORM_VERSION override below the minimum. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$adapter = new Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms\FluentFormsIntegration();
if ( $adapter->is_available() ) { throw new RuntimeException( 'Use inactive Fluent or an unsupported version fixture.' ); }
$status = Tunlamweb\VNAddressPro\Plugin::instance()->forms()->status();
if ( $status['fluent-forms']['booted'] || has_action( 'fluentform/render_item_vnap_province' ) || wp_script_is( 'vnap-fluent-forms', 'enqueued' ) ) { throw new RuntimeException( 'Unavailable adapter leaked hooks or assets.' ); }
if ( is_wp_error( Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_provinces() ) ) { throw new RuntimeException( 'Core depends on Fluent.' ); }
if ( defined( 'WPCF7_VERSION' ) && ! $status['contact-form-7']['booted'] ) { throw new RuntimeException( 'CF7 adapter was affected.' ); }
WP_CLI::success( 'Absent/unsupported Fluent adapter stays inactive; core/CF7 unaffected; no Fluent assets.' );
