<?php
namespace Tunlamweb\VNAddressPro\Compatibility;

defined( 'ABSPATH' ) || exit;

final class Legacy {
    private static bool $loaded = false;

    public static function load(): void {
        if ( self::$loaded ) {
            return;
        }
        // Preserve an already loaded class; a second separately installed copy is unsupported.
        if ( ! class_exists( 'VN_Address_API_2025', false ) ) {
            require_once VNAP_PATH . 'includes/legacy-api.php';
            \VN_Address_API_2025::init();
        }
        self::$loaded = true;
    }
}
