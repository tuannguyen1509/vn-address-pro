<?php
/** Disposable WordPress + supported WPForms Lite only; wp eval-file. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
use Tunlamweb\VNAddressPro\Integrations\Forms\WPForms\WPFormsIntegration;
use Tunlamweb\VNAddressPro\Plugin;
$n = 0; $check = static function ( $ok, $label ) use ( &$n ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$n; WP_CLI::line( 'PASS ' . $label ); };
$http = 0; $deny = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); }; add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$capture = static fn() => true; add_filter( 'pre_wp_mail', $capture, PHP_INT_MAX );
$actor = get_current_user_id(); $id = 0;
set_error_handler( static function ( $s, $m, $f, $l ) { if ( error_reporting() & $s ) { throw new ErrorException( $m, 0, $s, $f, $l ); } return false; } );
try {
    $check( ( new WPFormsIntegration() )->is_available(), 'native supported Lite APIs detected' );
    $field = apply_filters( 'wpforms_fields_get_field_object_vnap-address', null );
    $check( $field instanceof \WPForms_Field, 'custom address field registered through native registry' );
    $p = Plugin::instance()->address_service(); $a = $p->get_wards( 79 )['wards'][0]; $b = $p->get_wards( 1 )['wards'][0];
    $valid = array( 'province' => '79', 'ward' => (string) $a['code'], 'province_name' => 'forged', 'ward_name' => '<script>forged</script>' );
    $check( ! is_wp_error( $field->resolve( $valid, true ) ), 'valid pair accepted offline' );
    $check( $field->resolve( '', false ) === null && $field->resolve( array( 'province' => '', 'ward' => '' ), false ) === null, 'optional empty group accepted' );
    foreach ( array( '', array(), '79', array( 'province' => array( 79 ), 'ward' => $a['code'] ), array( 'province' => '79', 'ward' => array( $a['code'] ) ), array( 'province' => '79', 'ward' => $b['code'] ), array( 'province' => '79', 'ward' => '' ), array( 'province' => '999999', 'ward' => '1' ) ) as $bad ) { $check( is_wp_error( $field->resolve( $bad, true ) ), 'required malformed/partial/unknown/wrong-parent input rejected' ); }
    $check( is_wp_error( $field->resolve( array( 'province' => '', 'ward' => $a['code'] ), false ) ), 'partial optional group rejected' );
    wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
    $data = array( 'fields' => array( 1 => array( 'id' => 1, 'type' => 'vnap-address', 'label' => 'Home', 'required' => '1' ), 2 => array( 'id' => 2, 'type' => 'vnap-address', 'label' => 'Office' ) ), 'settings' => array( 'form_title' => 'VNAP integration fixture', 'submit_text' => 'Submit' ) );
    $id = wpforms()->obj( 'form' )->add( 'VNAP integration fixture', array( 'post_content' => wpforms_encode( $data ) ) );
    $check( $id > 0, 'form saved through native form API' );
    $form = wpforms_decode( get_post( $id )->post_content );
    $form['id'] = $id;
    wpforms()->obj( 'form' )->update( $id, $form );
    require_once WPFORMS_PLUGIN_DIR . 'includes/admin/builder/functions.php';
    ob_start(); $field->field_options( $form['fields'][1] ); $options = ob_get_clean();
    $check( str_contains( $options, 'required' ) && str_contains( $options, 'label' ), 'native builder settings rendered' );
    ob_start(); wpforms_display( $id ); $html = ob_get_clean();
    $check( substr_count( $html, 'data-vnap-address ' ) === 2 && str_contains( $html, 'wpforms[fields][1][province]' ) && str_contains( $html, 'wpforms[fields][2][ward]' ), 'real native form renders independent groups and correct names' );
    $check( substr_count( $html, 'data-vnap-search=' ) === 4 && ! str_contains( $html, 'data-vnap-address-line' ), 'shared searchable component used; street remains a native text field' );
    $field->format( 1, $valid, $form ); $formatted = wpforms()->obj( 'process' )->fields[1];
    $check( $formatted['province_code'] === '79' && $formatted['ward_code'] === (string) $a['code'], 'canonical codes retained separately' );
    $check( str_contains( $formatted['value'], $a['name'] ) && ! str_contains( $formatted['value'], 'forged' ), 'notification value resolved from authoritative names' );
    $check( json_decode( $formatted['value_raw'], true ) === array( 'province_code' => '79', 'ward_code' => (string) $a['code'] ), 'machine-readable codes retained in value_raw' );
    $field->validate( 1, array( 'province' => '79', 'ward' => $b['code'] ), $form );
    $check( ! empty( wpforms()->obj( 'process' )->errors[$id][1] ), 'native error bag receives relationship validation failure' );
    $check( $http === 0, 'zero outbound HTTP for rendering and validation' );
    WP_CLI::success( $n . ' WPForms Lite integration checks passed.' );
} finally {
    if ( $id ) { wp_delete_post( $id, true ); }
    wp_set_current_user( $actor ); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', $capture, PHP_INT_MAX ); restore_error_handler();
}
