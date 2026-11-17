<?php
namespace Tunlamweb\VNAddressPro\Support;
defined( 'ABSPATH' ) || exit;

final class IntegrationSettings {
    public const OPTION = 'vnap_integrations';
    public const IDS = array( 'woocommerce', 'contact-form-7', 'fluent-forms', 'wpforms' );

    public static function enabled( string $id ): bool {
        $settings = get_option( self::OPTION, array() );
        return ! is_array( $settings ) || ! array_key_exists( $id, $settings ) || $settings[ $id ] !== false;
    }

    public static function save( array $selected ): void {
        $settings = array();
        foreach ( self::IDS as $id ) { $settings[ $id ] = in_array( $id, $selected, true ); }
        update_option( self::OPTION, $settings, false );
    }
}
