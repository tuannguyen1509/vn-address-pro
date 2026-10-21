<?php
namespace Tunlamweb\VNAddressPro\Support;

defined( 'ABSPATH' ) || exit;

/** Avoid PHP 8 syntax so unsupported runtimes can show a notice. */
final class Requirements {
    public static function met() {
        global $wp_version;
        return version_compare( PHP_VERSION, VNAP_MIN_PHP, '>=' )
            && version_compare( (string) $wp_version, VNAP_MIN_WP, '>=' );
    }

    public static function message() {
        return sprintf(
            /* translators: 1: minimum PHP version, 2: minimum WordPress version. */
            __( 'VN Address Pro requires PHP %1$s or newer and WordPress %2$s or newer. Plugin core has not been loaded.', 'vn-address-pro' ),
            VNAP_MIN_PHP,
            VNAP_MIN_WP
        );
    }

    public static function notice() {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( self::message() ) . '</p></div>';
        }
    }

    public static function reject_activation() {
        wp_die( esc_html( self::message() ), esc_html__( 'Plugin requirements not met', 'vn-address-pro' ), array( 'back_link' => true ) );
    }

    public static function bootstrap_notice() {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'VN Address Pro could not start. Please reinstall a complete plugin package.', 'vn-address-pro' ) . '</p></div>';
        }
    }
}
