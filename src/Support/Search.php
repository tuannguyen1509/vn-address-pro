<?php
namespace Tunlamweb\VNAddressPro\Support;

defined( 'ABSPATH' ) || exit;

final class Search {
    public function contains_ci( string $haystack, string $needle ): bool {
        if ( $needle === '' ) {
            return true;
        }
        if ( function_exists( 'mb_stripos' ) ) {
            return mb_stripos( $haystack, $needle, 0, 'UTF-8' ) !== false;
        }
        return stripos( $haystack, $needle ) !== false;
    }
}
