<?php
/** Native duplication/import smoke test; disposable database only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
require_once __DIR__ . '/Fixture.php';
use Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms\Configuration;
$forms = array(); $file = ''; $actor = get_current_user_id();
$deny = static fn() => new WP_Error( 'offline' ); add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
set_error_handler( static function( $s, $m, $f, $l ) { if ( error_reporting() & $s ) { throw new ErrorException( $m, 0, $s, $f, $l ); } return false; } );
try {
    wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
    $form = vnap_test_fluent_form(); $forms[] = $form;
    $copy = ( new FluentForm\App\Services\Form\FormService() )->duplicate( array( 'form_id' => $form->id ) ); $forms[] = $copy;
    $raw = Configuration::fields( $copy );
    if ( ! Configuration::valid( $raw[1], $copy ) || $raw[1]['settings']['vnap_province_field'] !== 'home_province' ) { throw new RuntimeException( 'Native duplication lost the group reference' ); }
    // The public native importer consumes the same JSON structure emitted by exportForms.
    $export = $form->toArray(); $export['form_fields'] = json_decode( $form->form_fields, true );
    $export['metas'] = FluentForm\App\Models\FormMeta::where( 'form_id', $form->id )->get()->toArray();
    $file = wp_tempnam( 'vnap-synthetic-fluent.json' ); file_put_contents( $file, wp_json_encode( array( $export ) ) );
    $result = FluentForm\App\Services\Transfer\TransferService::importForms( new FluentForm\Framework\Http\Request\File( $file, 'synthetic.json', 'application/json' ) );
    $id = array_key_first( $result['inserted_forms'] ); $import = FluentForm\App\Models\Form::find( $id ); $forms[] = $import;
    $raw = Configuration::fields( $import );
    if ( ! Configuration::valid( $raw[1], $import ) ) { throw new RuntimeException( 'Native import lost the group reference' ); }
    $html = do_shortcode( '[fluentform id="' . $import->id . '"]' );
    if ( ! str_contains( $html, 'data-vnap-fluent-province="home_province"' ) ) { throw new RuntimeException( 'Imported form cannot render linked native fields' ); }
    WP_CLI::success( 'Native duplication, native JSON import and imported form rendering passed.' );
} finally {
    restore_error_handler(); wp_set_current_user( $actor ); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX );
    if ( $file && is_file( $file ) ) { unlink( $file ); }
    foreach ( $forms as $form ) { vnap_test_fluent_delete( $form ); }
}
