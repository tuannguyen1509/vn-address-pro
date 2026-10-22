<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7;
use Tunlamweb\VNAddressPro\Integrations\Forms\AddressFieldConfig;
defined( 'ABSPATH' ) || exit;

final class FormTags {
    public const TYPES = array( 'vnap_province','vnap_province*','vnap_ward','vnap_ward*','vnap_address','vnap_address*' );
    public function register(): void {
        wpcf7_add_form_tag( self::TYPES, array( new Renderer(), 'render' ), array( 'name-attr' => true ) );
    }
    public static function tags(): array { $form = \WPCF7_ContactForm::get_current(); return $form ? $form->scan_form_tags() : array(); }
    public static function config( \WPCF7_FormTag $tag ): AddressFieldConfig|\WP_Error {
        $tags = self::tags(); $same = array_filter( $tags, static fn( $t ) => $t->name === $tag->name );
        $group = (string) $tag->get_option( 'province', '', true );
        if ( ! $tag->name || count( $same ) !== 1 || ! in_array( $tag->type, self::TYPES, true ) ) { return self::error(); }
        if ( $tag->basetype === 'vnap_ward' ) {
            if ( count( (array) $tag->get_option( 'province' ) ) !== 1 ) { return self::error(); }
            $parent = array_values( array_filter( $tags, static fn( $t ) => $t->name === $group && $t->basetype === 'vnap_province' ) );
            if ( $group === '' || count( $parent ) !== 1 || count( array_filter( $tags, static fn( $t ) => $t->name === $group ) ) !== 1 ) { return self::error(); }
        }
        $first = $tag->values[0] ?? '';
        $placeholder = $tag->has_option( 'placeholder' ) ? $first : '';
        $default = $tag->get_default_option( $placeholder === '' ? $first : '' );
        return new AddressFieldConfig( $tag->name, substr( $tag->basetype, 5 ), $tag->is_required(), $group, is_scalar( $default ) ? (string) $default : '', '', $placeholder );
    }
    public static function error(): \WP_Error { return new \WP_Error( 'vnap_form_configuration', __( 'Address fields are misconfigured. Use unique field names and link each ward to a province field.', 'vn-address-pro' ) ); }
}
