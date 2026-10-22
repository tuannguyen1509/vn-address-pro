<?php
namespace Tunlamweb\VNAddressPro\Migration;
defined( 'ABSPATH' ) || exit;

/** Comparison only. Never supplies persisted/display text. */
final class AddressNormalizer {
    private const PREFIXES = array( 'province' => array( 'thanh pho','tinh','tp' ), 'ward' => array( 'phuong','thi tran','dac khu','xa','p','tt' ) );
    public const PROVINCE_ALIASES = array( 'hcm' => 'ho chi minh', 'hcmc' => 'ho chi minh' );
    public function administrative_type( string $text, string $entity ): ?string {
        $text = $this->normalize( $text );
        foreach ( self::PREFIXES[ $entity ] ?? array() as $prefix ) {
            if ( str_starts_with( $text, $prefix . ' ' ) ) { return array( 'tp' => 'thanh pho', 'p' => 'phuong', 'tt' => 'thi tran' )[ $prefix ] ?? $prefix; }
        }
        return null;
    }
    public function normalize( string $text, ?string $entity = null ): string {
        if ( class_exists( '\Normalizer' ) ) { $text = \Normalizer::normalize( $text, \Normalizer::FORM_C ) ?: $text; }
        $text = remove_accents( $text, 'vi' );
        $text = preg_replace( '/\p{M}+/u', '', $text ) ?? '';
        $text = strtolower( $text );
        $text = trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) ?? '' );
        if ( $entity !== null ) {
            foreach ( self::PREFIXES[ $entity ] ?? array() as $prefix ) {
                if ( str_starts_with( $text, $prefix . ' ' ) ) { return trim( substr( $text, strlen( $prefix ) ) ); }
            }
        }
        return $text;
    }
    public function district_like( string $text ): bool {
        return (bool) preg_match( '/\A(?:quan|huyen|q|h|thi xa|thanh pho|tp)(?: |\z)/', $this->normalize( $text ) );
    }
}
