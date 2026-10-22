<?php
namespace Tunlamweb\VNAddressPro\Migration;
defined( 'ABSPATH' ) || exit;

final class WardMatcher {
    public function match( string $raw, int $province, array $wards, $code = '' ): array {
        $wards = array_filter( $wards, static fn( $row ) => $row['province_code'] === $province );
        if ( $code !== '' && $code !== null ) {
            $id = NameMatcher::code( $code );
            return NameMatcher::result( array_filter( $wards, static fn( $row ) => $row['code'] === $id ), 'EXACT_CODE', 1.0 );
        }
        if ( ( new AddressNormalizer() )->district_like( $raw ) ) { return NameMatcher::result( array(), 'NO_MATCH', 0 ) + array( 'warning' => 'DISTRICT_ONLY' ); }
        return ( new NameMatcher() )->match( $raw, $wards, 'ward' );
    }
}
