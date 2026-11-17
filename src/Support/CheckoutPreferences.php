<?php
namespace Tunlamweb\VNAddressPro\Support;
defined( 'ABSPATH' ) || exit;

final class CheckoutPreferences {
    public const OPTION = 'vnap_checkout_preferences';
    public static function get( string $key ): bool {
        $defaults = array( 'css' => false, 'compact' => true, 'vat' => true );
        $saved = get_option( self::OPTION, array() );
        return (bool) apply_filters( 'vnap_checkout_' . $key, is_array( $saved ) && isset( $saved[ $key ] ) && is_bool( $saved[ $key ] ) ? $saved[ $key ] : ( $defaults[ $key ] ?? false ) );
    }
    public static function save( array $selected ): void {
        $settings = array();
        foreach ( array( 'css','compact','vat' ) as $key ) { $settings[ $key ] = in_array( $key, $selected, true ); }
        update_option( self::OPTION, $settings, false );
    }
}
