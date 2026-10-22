<?php
/** Disposable WordPress/CF7 only; wp eval-file. No real mail delivery. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'WPCF7_VERSION' ) ) { return; }
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7\{FormTags,Validator,ContactForm7Integration};
$checks = 0; $check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::line( 'PASS ' . $label ); };
$post = $_POST; $server = $_SERVER; $form = null; $http = 0; $mail = array();
$deny = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); }; add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$capture = static function ( $return, $atts ) use ( &$mail ) { $mail[] = $atts; return true; }; add_filter( 'pre_wp_mail', $capture, 10, 2 );
set_error_handler( static function ( $s, $m, $f, $l ) { if ( error_reporting() & $s ) { throw new ErrorException( $m, 0, $s, $f, $l ); } return false; } );
try {
    $service = Plugin::instance()->address_service(); $a = $service->get_wards( 79 )['wards'][0]; $b = $service->get_wards( 1 )['wards'][0];
    $adapter = new ContactForm7Integration(); $check( $adapter->is_available(), 'Actual CF7 API detected' );
    $before = count( $GLOBALS['wp_filter']['wpcf7_init']->callbacks[10] ); Plugin::instance()->load_forms(); Plugin::instance()->load_forms();
    $check( count( $GLOBALS['wp_filter']['wpcf7_init']->callbacks[10] ) === $before, 'Registry boot is idempotent' );
    $form = WPCF7_ContactForm::get_template();
    $template = '<label>Province [vnap_province* home default:1 "79"]</label><label>Ward [vnap_ward* home_ward province:home default:1 "' . $a['code'] . '"]</label><label>Street [vnap_address street]</label><label>Office [vnap_province office]</label><label>Office ward [vnap_ward office_ward province:office]</label>[submit "Send"]';
    $form->set_properties( array( 'form' => $template ) ); $form->save();
    $html = $form->form_html(); $tags = FormTags::tags();
    $map = array(); foreach ( $tags as $tag ) { if ( $tag->name ) { $map[$tag->name] = $tag; } }
    $check( count( array_filter( $tags, static fn( $t ) => in_array( $t->type, FormTags::TYPES, true ) ) ) === 5, 'Optional and required tags registered and parsed' );
    $check( substr_count( $html, 'data-vnap-cf7-kind="province"' ) === 2 && substr_count( $html, 'data-vnap-cf7-kind="ward"' ) === 2 && str_contains( $html, 'data-vnap-cf7-kind="address"' ), 'Multiple groups render native province/ward/address controls' );
    $check( preg_match( '/<option[^>]*(?:value="79"[^>]*selected|selected[^>]*value="79")/', $html ) && str_contains( $html, 'data-vnap-default-ward="' . $a['code'] . '"' ), 'Native quoted/default-index values retained' );
    preg_match( '/<select[^>]*name="home_ward"[^>]*>(.*?)<\/select>/s', $html, $ward_html ); $check( isset( $ward_html[1] ) && substr_count( $ward_html[1], '<option' ) === 1, 'No all-wards payload' );
    $check( str_contains( $html, 'aria-required="true"' ) && str_contains( $html, 'wpcf7-validates-as-required' ) && str_contains( $html, 'data-name="home_ward"' ), 'Native CF7 accessibility/error association conventions' );
    $validator = new Validator(); $data = array( 'home' => '79', 'home_ward' => (string) $a['code'], 'street' => '', 'office' => '', 'office_ward' => '', 'home_name' => 'FORGED' );
    $check( $validator->selection( $map['home_ward'], $data )['ward']['name'] === $a['name'], 'Shared core resolves valid pair and ignores forged browser names' );
    $check( ! is_wp_error( $validator->selection( $map['street'], $data ) ), 'Optional street empty valid' );
    $check( ! is_wp_error( $validator->selection( $map['office_ward'], $data ) ), 'Optional pair both empty valid' );
    foreach ( array( array( 'home' => '' ), array( 'home_ward' => '' ), array( 'home' => array( '79' ) ), array( 'home_ward' => array( $a['code'] ) ), array( 'home' => '999999999' ), array( 'home_ward' => '999999999' ), array( 'home_ward' => (string) $b['code'] ), array( 'home' => '79x' ), array( 'home_ward' => ' 1' ) ) as $bad ) {
        $error = $validator->selection( $map['home_ward'], array_replace( $data, $bad ) ); $native = new WPCF7_Validation(); if ( is_wp_error( $error ) ) { $native->invalidate( $map['home_ward'], $error ); }
        $check( ! $native->is_valid(), 'Malformed/missing/mismatched pair invalidates native CF7 result' );
    }
    $check( is_wp_error( $validator->selection( $map['home'], array_replace( $data, array( 'home' => '' ) ) ) ), 'Required province empty invalid' );
    $check( is_wp_error( $validator->selection( $map['office_ward'], array_replace( $data, array( 'office' => '79' ) ) ) ), 'Optional selected province requires its present ward' );
    foreach ( array( '[vnap_ward orphan province:missing]', '[vnap_province same][vnap_province same][vnap_ward w province:same]', '[vnap_province p][vnap_ward w province:p province:p]', '[vnap_address* required_street]' ) as $template_bad ) {
        $form->set_properties( array( 'form' => $template_bad ) );
        $rendered = $form->form_html(); $newtags = FormTags::tags(); $tag = end( $newtags );
        $check( is_wp_error( $validator->selection( $tag, array() ) ), 'Misconfiguration/duplicate names/required street fails safely' );
    }
    $form->set_properties( array( 'form' => '[vnap_province p placeholder "<script>bad</script>"]' ) );
    $escaped = $form->form_html(); $check( ! str_contains( $escaped, '<script>bad</script>' ), 'Canonical/options/placeholder output escaped' );
    $properties = $form->get_properties(); $properties['form'] = $template;
    foreach ( array( 'mail','mail_2' ) as $key ) { $properties[$key] = array( 'active' => true, 'subject' => 'Synthetic address test', 'sender' => 'Test <sender@example.invalid>', 'recipient' => 'receiver@example.invalid', 'body' => '[home]|[home_ward]|[_vnap_code_home]|[_vnap_code_home_ward]|[street]', 'additional_headers' => '', 'attachments' => '', 'use_html' => $key === 'mail_2', 'exclude_blank' => false ); }
    $form->set_properties( $properties ); $form->save(); $form->form_html();
    $_POST = $data + array( '_wpcf7_unit_tag' => 'wpcf7-f' . $form->id() . '-o1', '_wpcf7_container_post' => 0, '_wpcf7_locale' => 'en_US' );
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'Synthetic integration test';
    $result = $form->submit( array( 'skip_spam_check' => true ) );
    $check( $result['status'] === 'mail_sent', 'Actual CF7 submission validates and sends through captured native mail pipeline' );
    $check( count( $mail ) === 2, 'Both Mail and Mail (2) captured without SMTP' );
    foreach ( $mail as $message ) { $check( str_contains( $message['message'], $a['name'] ) && str_contains( $message['message'], '|79|' ) && str_contains( $message['message'], '|' . $a['code'] . '|' ) && ! str_contains( $message['message'], 'FORGED' ), 'Names canonical, explicit code mail-tags usable, forged names excluded' ); }
    $check( WPCF7_Submission::get_instance()->get_posted_data( 'home' ) === '79', 'Submitted field remains canonical code for consumers' );
    $check( $http === 0, 'Entire form integration runs offline: zero remote calls' );
    WP_CLI::success( $checks . ' CF7 integration checks passed; version=' . WPCF7_VERSION );
} finally {
    restore_error_handler(); $_POST = $post; $_SERVER = $server; if ( $form && $form->id() ) { wp_delete_post( $form->id(), true ); }
    remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', $capture );
}
