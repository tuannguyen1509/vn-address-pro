<?php
/** Disposable synthetic CF7 forms/pages only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$ward = Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_wards( 79 )['wards'][0]; $forms = array();
foreach ( array( false, true ) as $defaults ) {
    $form = WPCF7_ContactForm::get_template();
    $template = '<label>Name [text* contact_name]</label><label>Email [email* contact_email]</label><label>Home province [vnap_province* home' . ( $defaults ? ' default:1 "79"' : '' ) . ']</label><label>Home ward [vnap_ward* home_ward province:home' . ( $defaults ? ' default:1 "' . $ward['code'] . '"' : '' ) . ']</label><label>Street [vnap_address street]</label><label>Office province [vnap_province office]</label><label>Office ward [vnap_ward office_ward province:office]</label>[submit "Send"]';
    $properties = $form->get_properties(); $properties['form'] = $template;
    foreach ( array( 'mail','mail_2' ) as $key ) { $properties[$key] = array( 'active' => true, 'subject' => 'Synthetic CF7 address test', 'sender' => 'Test <sender@example.invalid>', 'recipient' => 'receiver@example.invalid', 'body' => '[home]|[home_ward]|[_vnap_code_home]|[_vnap_code_home_ward]|[street]', 'additional_headers' => '', 'attachments' => '', 'use_html' => $key === 'mail_2', 'exclude_blank' => false ); }
    $form->set_properties( $properties ); $form->save(); $forms[] = $form->id();
}
$plain = WPCF7_ContactForm::get_template(); $plain->save();
$page = wp_insert_post( array( 'post_type' => 'page','post_status' => 'publish','post_title' => 'Synthetic Address Forms','post_content' => '[contact-form-7 id="' . $forms[0] . '"][contact-form-7 id="' . $forms[1] . '"][vn_address_fields]' ) );
$plain_page = wp_insert_post( array( 'post_type' => 'page','post_status' => 'publish','post_title' => 'Synthetic Plain Form','post_content' => '[contact-form-7 id="' . $plain->id() . '"]' ) );
update_option( 'show_avatars', 0 );
WP_CLI::line( wp_json_encode( array( 'page' => get_permalink( $page ), 'plain' => get_permalink( $plain_page ), 'forms' => $forms, 'ward' => $ward, 'province' => Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_wards( 79 )['province'] ) ) );
