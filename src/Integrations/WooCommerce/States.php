<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
use Tunlamweb\VNAddressPro\Plugin;
defined( 'ABSPATH' ) || exit;

final class States {
    public static function to_wc_state_code( int $code ): string { return $code > 0 ? 'VN-' . $code : ''; }
    public static function from_wc_state_code( string $state ): ?int {
        if ( ! preg_match( '/\AVN-([1-9][0-9]*)\z/', $state, $m ) || (string) (int) $m[1] !== $m[1] ) { return null; }
        return (int) $m[1];
    }
    public function register(): void { add_filter( 'woocommerce_states', array( $this, 'filter' ) ); }
    public function filter( array $states ): array {
        $items = Plugin::instance()->address_service()->get_provinces();
        if ( ! is_wp_error( $items ) ) {
            $states['VN'] = array();
            foreach ( $items as $p ) { $states['VN'][ self::to_wc_state_code( $p['code'] ) ] = $p['name']; }
        }
        return $states;
    }
}
