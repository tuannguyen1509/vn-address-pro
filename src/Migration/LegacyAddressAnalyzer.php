<?php
namespace Tunlamweb\VNAddressPro\Migration;
use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\States;
defined( 'ABSPATH' ) || exit;

final class LegacyAddressAnalyzer {
    public function __construct( private AddressRepositoryInterface $repository ) {}
    public function analyze( string $type, int $id, string $address, array $original, bool $order_display = false ): LegacyAddressAnalysis {
        $r = array( 'source_type' => $type, 'entity_id' => $id, 'address_type' => $address, 'original' => $original,
            'province_candidate' => null, 'ward_candidate' => null, 'province_confidence' => 'NONE', 'ward_confidence' => 'NONE',
            'overall_confidence' => 'NONE', 'score' => 0, 'status' => 'INVALID_DATA', 'warnings' => array(), 'proposed_changes' => array(), 'target' => $original );
        if ( ! empty( $original['_invalid'] ) ) { return new LegacyAddressAnalysis( $r ); }
        foreach ( array( 'country','state','city' ) as $key ) { if ( ! is_string( $original[ $key ] ?? null ) || strlen( $original[ $key ] ) > 512 || ! preg_match( '//u', $original[ $key ] ) ) { return new LegacyAddressAnalysis( $r ); } }
        foreach ( array( 'province_meta','ward_meta','province_name_meta' ) as $key ) {
            if ( ! is_array( $original[ $key ] ?? null ) || ! is_bool( $original[ $key ]['exists'] ?? null ) || ! is_scalar( $original[ $key ]['value'] ?? null ) || strlen( (string) $original[ $key ]['value'] ) > 512 ) { return new LegacyAddressAnalysis( $r ); }
        }
        if ( $original['country'] === '' ) { $r['status'] = 'NEEDS_REVIEW'; $r['warnings'][] = 'UNKNOWN_COUNTRY'; return new LegacyAddressAnalysis( $r ); }
        if ( $original['country'] !== 'VN' ) { $r['status'] = 'NON_VIETNAM'; return new LegacyAddressAnalysis( $r ); }
        $provinces = $this->repository->get_provinces();
        if ( is_wp_error( $provinces ) ) { $r['status'] = 'UNRESOLVED'; return new LegacyAddressAnalysis( $r ); }
        $p = ( new ProvinceMatcher() )->match( $original['state'], $provinces ); $r['province_candidate'] = $p;
        $r['province_confidence'] = NameMatcher::confidence( $p['score'] );
        if ( ! $p['matched'] ) { $r['status'] = $p['strategy'] === 'AMBIGUOUS' ? 'AMBIGUOUS' : 'UNRESOLVED'; return new LegacyAddressAnalysis( $r ); }
        $province = $p['candidate'];
        if ( isset( $p['warning'] ) ) { $r['warnings'][] = $p['warning']; }
        if ( $original['province_meta']['exists'] && $original['province_meta']['value'] !== '' && NameMatcher::code( $original['province_meta']['value'] ) !== $province['code'] ) {
            $r['status'] = 'NEEDS_REVIEW'; $r['warnings'][] = 'PROVINCE_META_CONFLICT'; return new LegacyAddressAnalysis( $r );
        }
        $detail = $this->repository->get_wards( $province['code'] );
        if ( is_wp_error( $detail ) ) { $r['status'] = 'UNRESOLVED'; return new LegacyAddressAnalysis( $r ); }
        $w = ( new WardMatcher() )->match( $original['city'], $province['code'], $detail['wards'], $original['ward_meta']['value'] );
        $r['ward_candidate'] = $w; $r['ward_confidence'] = NameMatcher::confidence( $w['score'] );
        if ( ! $w['matched'] ) {
            $r['status'] = $w['strategy'] === 'AMBIGUOUS' ? 'AMBIGUOUS' : ( isset( $w['warning'] ) ? 'NEEDS_REVIEW' : 'UNRESOLVED' );
            if ( isset( $w['warning'] ) ) { $r['warnings'][] = $w['warning']; }
            return new LegacyAddressAnalysis( $r );
        }
        $ward = $w['candidate']; $r['score'] = min( $p['score'], $w['score'] ); $r['overall_confidence'] = NameMatcher::confidence( $r['score'] );
        if ( isset( $w['warning'] ) ) { $r['warnings'][] = $w['warning']; }
        $target = $original;
        foreach ( array( 'province_meta' => $province['code'], 'ward_meta' => $ward['code'] ) as $key => $value ) {
            if ( ! $original[ $key ]['exists'] || (string) $original[ $key ]['value'] !== (string) $value ) { $target[ $key ] = array( 'exists' => true, 'value' => (string) $value ); }
        }
        if ( $type === 'customer' || $order_display ) {
            $target['state'] = States::to_wc_state_code( $province['code'] ); $target['city'] = $ward['name'];
            if ( $type === 'order' ) { $target['province_name_meta'] = array( 'exists' => true, 'value' => $province['name'] ); }
        } elseif ( $original['state'] !== States::to_wc_state_code( $province['code'] ) || $original['city'] !== $ward['name'] ) { $r['warnings'][] = 'HISTORICAL_SNAPSHOT_RETAINED'; }
        foreach ( $target as $key => $value ) { if ( $value !== $original[ $key ] ) { $r['proposed_changes'][ $key ] = array( 'before' => $original[ $key ], 'after' => $value ); } }
        $r['target'] = $target;
        $r['status'] = $r['proposed_changes'] ? ( $r['overall_confidence'] === 'HIGH' ? 'MIGRATABLE_HIGH' : 'MIGRATABLE_MEDIUM' ) : ( $r['warnings'] ? 'NEEDS_REVIEW' : 'ALREADY_CANONICAL' );
        return new LegacyAddressAnalysis( $r );
    }
}
