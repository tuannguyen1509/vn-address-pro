<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms;
defined( 'ABSPATH' ) || exit;

/** Inspect raw fields so duplicate names cannot disappear in Fluent's keyed parser. */
final class Configuration {
    public static function name( $value ): bool {
        return is_string( $value ) && (bool) preg_match( '/\A[a-zA-Z][a-zA-Z0-9_]*\z/', $value );
    }
    public static function fields( $form ): array {
        $definition = json_decode( $form->form_fields ?? '', true );
        $flat = array();
        $walk = static function ( array $fields ) use ( &$walk, &$flat ): void {
            foreach ( $fields as $field ) {
                if ( ! is_array( $field ) ) { continue; }
                if ( ( $field['element'] ?? '' ) === 'container' ) {
                    foreach ( $field['columns'] ?? array() as $column ) { $walk( $column['fields'] ?? array() ); }
                } else { $flat[] = $field; }
            }
        };
        $walk( $definition['fields'] ?? array() );
        return $flat;
    }
    public static function valid( array $field, $form ): bool {
        $name = $field['attributes']['name'] ?? null;
        if ( ! self::name( $name ) ) { return false; }
        $fields = self::fields( $form );
        $matches = array_values( array_filter( $fields, static fn( $item ) => ( $item['attributes']['name'] ?? null ) === $name ) );
        if ( count( $matches ) !== 1 ) { return false; }
        if ( ( $field['element'] ?? '' ) !== 'vnap_ward' ) { return true; }
        $reference = $field['settings']['vnap_province_field'] ?? null;
        if ( ! self::name( $reference ) || $reference === $name ) { return false; }
        $matches = array_values( array_filter( $fields, static fn( $item ) => ( $item['attributes']['name'] ?? null ) === $reference ) );
        return count( $matches ) === 1 && ( $matches[0]['element'] ?? '' ) === 'vnap_province';
    }
}
