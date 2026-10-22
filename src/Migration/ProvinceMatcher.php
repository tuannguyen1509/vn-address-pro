<?php
namespace Tunlamweb\VNAddressPro\Migration;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\States;
defined( 'ABSPATH' ) || exit;

final class ProvinceMatcher {
    public function match( string $raw, array $provinces ): array {
        $canonical = States::from_wc_state_code( $raw ); $code = $canonical ?? NameMatcher::code( $raw );
        if ( $code !== null ) {
            $r = NameMatcher::result( array_filter( $provinces, static fn( $row ) => $row['code'] === $code ), 'EXACT_CODE', $canonical === null ? 0.85 : 1.0 );
            return $canonical === null ? $r + array( 'warning' => 'BARE_PROVINCE_CODE_UNVERIFIED' ) : $r;
        }
        return ( new NameMatcher() )->match( $raw, $provinces, 'province' );
    }
}
