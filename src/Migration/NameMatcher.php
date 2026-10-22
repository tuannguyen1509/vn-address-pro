<?php
namespace Tunlamweb\VNAddressPro\Migration;
defined( 'ABSPATH' ) || exit;

final class NameMatcher {
    public static function result( array $rows, string $strategy, float $score ): array {
        $ambiguous = count( $rows ) > 1;
        return array( 'matched' => count( $rows ) === 1, 'strategy' => $ambiguous ? 'AMBIGUOUS' : ( $rows ? $strategy : 'NO_MATCH' ),
            'score' => $ambiguous ? 0.2 : ( $rows ? $score : 0.0 ), 'candidates' => array_slice( array_values( $rows ), 0, 10 ), 'candidate' => count( $rows ) === 1 ? array_values( $rows )[0] : null );
    }
    public function match( string $raw, array $rows, string $entity ): array {
        $n = new AddressNormalizer();
        if ( trim( $raw ) === '' ) { return self::result( array(), 'NO_MATCH', 0 ); }
        foreach ( array( 'EXACT_CANONICAL_NAME' => 1.0, 'EXACT_NORMALIZED_NAME' => 0.98, 'UNIQUE_NORMALIZED_MATCH' => 0.96 ) as $strategy => $score ) {
            $matches = array_filter( $rows, static function ( $row ) use ( $raw, $n, $entity, $strategy ) {
                if ( $strategy === 'EXACT_CANONICAL_NAME' ) { return trim( $raw ) === $row['name']; }
                $strip = $strategy === 'UNIQUE_NORMALIZED_MATCH' ? $entity : null;
                return $n->normalize( $raw, $strip ) !== '' && $n->normalize( $raw, $strip ) === $n->normalize( $row['name'], $strip );
            } );
            if ( $matches ) {
                $type = $n->administrative_type( $raw, $entity ); $row = count( $matches ) === 1 ? array_values( $matches )[0] : null;
                if ( $strategy === 'UNIQUE_NORMALIZED_MATCH' && $row && $type !== null && $type !== $n->administrative_type( $row['name'], $entity ) ) {
                    return self::result( $matches, $strategy, 0.82 ) + array( 'warning' => 'ADMINISTRATIVE_PREFIX_CHANGED' );
                }
                return self::result( $matches, $strategy, $score );
            }
        }
        if ( $entity === 'province' ) {
            $alias = AddressNormalizer::PROVINCE_ALIASES[ $n->normalize( $raw, 'province' ) ] ?? null;
            if ( $alias !== null ) { return self::result( array_filter( $rows, static fn( $row ) => $n->normalize( $row['name'], 'province' ) === $alias ), 'EXACT_ALIAS', 0.85 ); }
        }
        return self::result( array(), 'NO_MATCH', 0 );
    }
    public static function confidence( float $score ): string { return $score >= 0.95 ? 'HIGH' : ( $score >= 0.8 ? 'MEDIUM' : ( $score > 0 ? 'LOW' : 'NONE' ) ); }
    public static function code( $value ): ?int {
        return ( is_int( $value ) || is_string( $value ) ) && preg_match( '/\A[1-9][0-9]*\z/', (string) $value ) && (string) (int) $value === (string) $value ? (int) $value : null;
    }
}
