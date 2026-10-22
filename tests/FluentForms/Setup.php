<?php
/** Disposable synthetic forms/pages only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
require_once __DIR__ . '/Fixture.php';
$a = vnap_test_fluent_form(); $b = vnap_test_fluent_form( false );
$definition = json_decode( $b->form_fields, true );
foreach ( array( 0, 1 ) as $index ) {
    $field = $definition['fields'][$index]; $kind = $index === 0 ? 'province' : 'ward';
    $field['attributes']['name'] = 'conditional_' . $kind; $field['uniqElKey'] = 'vnap_conditional_' . $kind;
    $field['settings']['vnap_province_field'] = 'conditional_province';
    $field['settings']['validation_rules']['required']['value'] = true;
    $field['settings']['conditional_logics'] = array( 'status' => true, 'type' => 'all', 'conditions' => array( array( 'field' => 'contact_name', 'operator' => '=', 'value' => 'Show' ) ) );
    $definition['fields'][] = $field;
}
wpFluent()->table( 'fluentform_forms' )->where( 'id', $b->id )->update( array( 'form_fields' => wp_json_encode( $definition ) ) );
$coexist = '';
if ( class_exists( 'WPCF7_ContactForm' ) ) {
    $cf7 = WPCF7_ContactForm::get_template();
    $properties = $cf7->get_properties(); $properties['form'] = '[vnap_province cf_province][vnap_ward cf_ward province:cf_province][submit "Send CF7"]';
    $cf7->set_properties( $properties ); $cf7->save(); $coexist = '[contact-form-7 id="' . $cf7->id() . '"]';
}
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic Fluent Forms', 'post_content' => '[fluentform id="' . $a->id . '"][fluentform id="' . $b->id . '"]' . $coexist . '[vn_address_fields]' ) );
update_option( 'show_avatars', 0 );
WP_CLI::line( wp_json_encode( array( 'page' => get_permalink( $page ), 'forms' => array( $a->id, $b->id ), 'ward' => Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_wards( 79 )['wards'][0] ) ) );
