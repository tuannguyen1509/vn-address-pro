<?php
/** Run after BrowserTest on the same disposable database with OfflineFixture MU installed. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$entry = wpFluent()->table( 'fluentform_submissions' )->orderBy( 'id', 'desc' )->first();
if ( ! $entry ) { throw new RuntimeException( 'Browser submission missing' ); }
$values = json_decode( $entry->response, true );
if ( ( $values['home_province'] ?? null ) !== '79' || ! is_string( $values['home_ward'] ?? null ) ) { throw new RuntimeException( 'Canonical entry code strings were lost' ); }
$selection = Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->validate_selection( $values['home_province'], $values['home_ward'] );
if ( is_wp_error( $selection ) ) { throw new RuntimeException( 'Stored pair is invalid' ); }
$form = FluentForm\App\Models\Form::find( $entry->form_id );
$display = FluentForm\App\Modules\Form\FormDataParser::parseFormEntry( $entry, $form, null, true );
if ( $display->user_inputs['home_ward'] !== esc_html( $selection['ward']['name'] ) ) { throw new RuntimeException( 'Stored entry does not format readably' ); }
$mail = get_option( 'vnap_test_fluent_mail', array() );
if ( ! $mail ) { throw new RuntimeException( 'Native notification was not captured' ); }
$message = end( $mail );
if ( ! str_contains( $message, $selection['province']['name'] . '|' . $selection['ward']['name'] . '|79|' . $selection['ward']['code'] ) || str_contains( $message, 'FORGED' ) ) { throw new RuntimeException( 'Native notification has incorrect address data' ); }
if ( (int) get_option( 'vnap_test_fluent_provider_http', 0 ) !== 0 ) { throw new RuntimeException( 'Offline run attempted address provider HTTP' ); }
WP_CLI::success( 'Entry code strings, readable native entry view, captured notification names/codes and zero address provider HTTP passed. Other blocked WP/Woo HTTP attempts=' . (int) get_option( 'vnap_test_fluent_http', 0 ) );
