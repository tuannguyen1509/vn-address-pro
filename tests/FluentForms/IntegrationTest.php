<?php
/** wp eval-file; disposable WP + real Fluent Forms, no SMTP. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
require_once __DIR__ . '/Fixture.php';
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms\{Configuration,FluentFormsIntegration};
use FluentForm\App\Modules\Form\{FormFieldsParser,FormDataParser};
use FluentForm\App\Services\FormBuilder\ShortCodeParser;
$count = 0; $check = static function( $ok, $label ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$count; WP_CLI::line( 'PASS ' . $label ); };
$http = 0; $deny = static function() use ( &$http ) { ++$http; return new WP_Error( 'offline' ); };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$forms = array();
set_error_handler( static function( $s, $m, $f, $l ) { if ( error_reporting() & $s ) { throw new ErrorException( $m, 0, $s, $f, $l ); } return false; } );
try {
    $check( (new FluentFormsIntegration())->is_available(), 'Real Fluent public APIs detected' );
    $before = count( $GLOBALS['wp_filter']['fluentform/render_item_vnap_ward']->callbacks[10] );
    Plugin::instance()->load_forms(); Plugin::instance()->load_forms();
    $check( count( $GLOBALS['wp_filter']['fluentform/render_item_vnap_ward']->callbacks[10] ) === $before, 'Single registry boot' );
    $form = vnap_test_fluent_form(); $forms[] = $form;
    $a = Plugin::instance()->address_service()->get_wards( 79 )['wards'][0]; $b = Plugin::instance()->address_service()->get_wards( 1 )['wards'][0];
    $data = array( 'home_province' => '79', 'home_ward' => (string) $a['code'], 'office_province' => '', 'office_ward' => '', 'home_name' => 'FORGED' );
    $fields = FormFieldsParser::getInputs( $form, array( 'raw', 'rules' ) );
    $validate = static function( $name, $values, $f = null, $active = null ) use ( $form, $fields ) {
        $native = ( $active ?? $fields )[$name]; $native['name'] = $name;
        return apply_filters( 'fluentform/validate_input_item_' . $native['element'], '', $native, $values, $active ?? $fields, $f ?? $form );
    };
    foreach ( array_keys( $fields ) as $name ) { $check( ! $validate( $name, $data ), 'Valid optional/required group: ' . $name ); }
    foreach ( array( array( 'home_province' => '' ), array( 'home_ward' => '' ), array( 'home_province' => array( '79' ) ), array( 'home_ward' => array( $a['code'] ) ), array( 'home_province' => '99999' ), array( 'home_ward' => '9999999' ), array( 'home_ward' => (string) $b['code'] ), array( 'home_ward' => '1x' ) ) as $bad ) {
        $check( (bool) $validate( 'home_ward', array_replace( $data, $bad ) ), 'Tampered/missing pair rejected' );
    }
    $check( (bool) $validate( 'home_province', array_replace( $data, array( 'home_province' => array() ) ) ), 'Province array rejected independently' );
    $check( (bool) $validate( 'office_ward', array_replace( $data, array( 'office_province' => '79' ) ) ), 'Selected optional province requires present ward' );
    $active = $fields; unset( $active['home_province'] );
    $check( (bool) $validate( 'home_ward', $data, null, $active ), 'Active ward cannot rely on inactive province' );
    $html = do_shortcode( '[fluentform id="' . $form->id . '"]' );
    $check( substr_count( $html, 'data-vnap-fluent-kind="province"' ) === 2 && substr_count( $html, 'data-vnap-fluent-kind="ward"' ) === 2, 'Native fields render multiple groups' );
    preg_match( '/<select[^>]*name="home_ward"[^>]*>(.*?)<\/select>/s', $html, $match );
    $check( isset( $match[1] ) && substr_count( $match[1], '<option' ) === 1, 'No wards embedded at render' );
    $display = FormDataParser::parseData( (object) $data, FormFieldsParser::getEntryInputs( $form ), $form->id, true );
    $check( $display['home_ward'] === esc_html( $a['name'] ) && $display['home_province'] !== '79', 'Native entry display resolves canonical names' );
    $mail = ShortCodeParser::parse( '{inputs.home_province}|{inputs.home_ward}|{inputs.home_province.value}|{inputs.home_ward.value}', false, $data, $form );
    $check( str_contains( $mail, $a['name'] ) && str_contains( $mail, '|79|' . $a['code'] ) && ! str_contains( $mail, 'FORGED' ), 'Native notification smartcodes show names and raw codes' );
    $raw = Configuration::fields( $form ); $raw[1]['settings']['vnap_province_field'] = 'missing';
    $badform = vnap_test_fluent_form( true, $raw ); $forms[] = $badform;
    $check( ! Configuration::valid( $raw[1], $badform ), 'Missing explicit reference detected' );
    $badhtml = do_shortcode( '[fluentform id="' . $badform->id . '"]' );
    $check( str_contains( $badhtml, 'configuration or data is unavailable' ), 'Configuration error renders safely' );
    $raw[1]['settings']['vnap_province_field'] = 'home_province'; $raw[] = $raw[0];
    $duplicate = vnap_test_fluent_form( true, $raw ); $forms[] = $duplicate;
    $check( ! Configuration::valid( $raw[1], $duplicate ), 'Duplicate reference names detected before keyed parser hides them' );
    $serialized = json_decode( wp_json_encode( json_decode( $form->form_fields, true ) ), true );
    $check( $serialized['fields'][1]['settings']['vnap_province_field'] === 'home_province', 'Native JSON preserves deterministic references' );
    $conditional = Configuration::fields( $form );
    foreach ( array( 0, 1 ) as $i ) {
        $conditional[$i]['settings']['conditional_logics'] = array( 'status' => true, 'type' => 'all', 'conditions' => array( array( 'field' => 'contact_name', 'operator' => '=', 'value' => 'Show' ) ) );
    }
    $hidden = vnap_test_fluent_form( true, $conditional ); $forms[] = $hidden;
    $essential = FormFieldsParser::getEssentialInputs( $hidden, array( 'contact_name' => 'Hide' ), array( 'raw', 'rules' ) );
    $check( ! isset( $essential['home_province'], $essential['home_ward'] ) && isset( $essential['office_province'] ), 'Native conditional assessor excludes inactive required group' );
    $check( $http === 0, 'Offline render/validation/entry/smartcodes: zero remote calls' );
    WP_CLI::success( $count . ' Fluent Forms checks; version=' . FLUENTFORM_VERSION );
} finally {
    restore_error_handler(); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX );
    foreach ( $forms as $form ) { vnap_test_fluent_delete( $form ); }
}
