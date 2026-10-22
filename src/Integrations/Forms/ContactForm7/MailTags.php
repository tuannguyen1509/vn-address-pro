<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7;
defined( 'ABSPATH' ) || exit;

final class MailTags {
    /** Ordinary [field] is readable; explicit [_vnap_code_field] retains codes. */
    public function replace( $replaced, $submitted, $html, $mail_tag ) {
        $name = $mail_tag->tag_name(); $code = str_starts_with( $name, '_vnap_code_' );
        $field = $code ? substr( $name, 11 ) : $name;
        $tag = null;
        foreach ( FormTags::tags() as $candidate ) { if ( $candidate->name === $field && in_array( $candidate->basetype, array( 'vnap_province','vnap_ward' ), true ) ) { $tag = $candidate; break; } }
        if ( ! $tag ) { return $replaced; }
        $submission = \WPCF7_Submission::get_instance(); if ( ! $submission ) { return ''; }
        $selection = ( new Validator() )->selection( $tag, $submission->get_posted_data() );
        if ( is_wp_error( $selection ) || ! $selection ) { return ''; }
        $row = $selection[$tag->basetype === 'vnap_ward' ? 'ward' : 'province'];
        $value = (string) $row[$code ? 'code' : 'name'];
        return $html ? esc_html( $value ) : $value;
    }
}
