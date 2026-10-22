<?php
namespace Tunlamweb\VNAddressPro\Migration;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\AddressValidator;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\{CustomerAddress,OrderAddress};
defined( 'ABSPATH' ) || exit;

/** Only Woo/WordPress query and CRUD APIs. No order storage assumptions. */
final class WooAddressSource {
    public function load( string $type, int $id ) {
        if ( $type === 'customer' ) {
            if ( ! get_userdata( $id ) || ( is_multisite() && ! is_user_member_of_blog( $id ) ) ) { return null; }
            $entity = new \WC_Customer( $id );
        } elseif ( $type === 'order' ) { $entity = wc_get_order( $id ); if ( ! $entity || $entity->get_type() !== 'shop_order' ) { return null; } }
        else { return null; }
        $entity->read_meta_data( true );
        return $entity;
    }
    public function snapshot( $entity, string $type, string $address ): array {
        $s = array();
        foreach ( array( 'country','state','city' ) as $part ) { $s[ $part ] = $entity->{"get_{$address}_{$part}"}( 'edit' ); }
        foreach ( array( 'province_meta' => 'province_code', 'ward_meta' => 'ward_code', 'province_name_meta' => 'province_name' ) as $part => $meta ) {
            $key = AddressValidator::meta( $address, $meta );
            $exists = $type !== 'customer' || $part !== 'province_name_meta' ? $entity->meta_exists( $key ) : false;
            $value = $exists ? $entity->get_meta( $key, true, 'edit' ) : '';
            $s[ $part ] = array( 'exists' => $exists, 'value' => $value );
        }
        // Malformed fields are not migration candidates and must not smuggle arbitrary
        // serialized customer data into the preview/audit. Normal strings stay untouched.
        $invalid = false;
        foreach ( $s as $key => &$value ) {
            $raw = is_array( $value ) ? $value['value'] : $value;
            if ( ! ( is_string( $raw ) || is_int( $raw ) ) || strlen( (string) $raw ) > 512 || ! preg_match( '//u', (string) $raw ) ) {
                $invalid = true; if ( is_array( $value ) ) { $value['value'] = '[invalid]'; } else { $value = '[invalid]'; }
            } elseif ( is_array( $value ) ) { $value['value'] = (string) $raw; }
        }
        unset( $value );
        if ( $invalid ) { $s['_invalid'] = true; }
        return $s;
    }
    public static function fingerprint( array $snapshot ): string { return hash( 'sha256', wp_json_encode( $snapshot ) ); }
    public function ids( string $type, array $scope, int $limit ): array {
        // WC 8.2's CPT query silently ignores `include`. Resolve an explicit,
        // bounded allowlist through CRUD instead of relying on storage-specific args.
        if ( $type === 'order' && $scope['include'] ) {
            $ids = array();
            foreach ( $scope['include'] as $id ) {
                $order = $this->load( 'order', $id ); if ( ! $order ) { continue; }
                $created = $order->get_date_created(); $day = $created ? $created->date( 'Y-m-d' ) : '';
                if ( ( $scope['from'] !== '' && $day < $scope['from'] ) || ( $scope['to'] !== '' && $day > $scope['to'] ) ) { continue; }
                $ids[] = $id;
            }
            sort( $ids, SORT_NUMERIC );
            return array_slice( $ids, ( $scope['page'] - 1 ) * $limit, $limit + 1 );
        }
        $args = array( 'orderby' => 'ID', 'order' => 'ASC', 'offset' => ( $scope['page'] - 1 ) * $limit );
        if ( $scope['include'] ) { $args['include'] = $scope['include']; }
        if ( $type === 'customer' ) { return array_map( 'intval', get_users( $args + array( 'fields' => 'ID', 'number' => $limit + 1 ) ) ); }
        $args += array( 'type' => 'shop_order', 'return' => 'ids', 'limit' => $limit + 1 );
        if ( $scope['from'] !== '' || $scope['to'] !== '' ) { $args['date_created'] = ( $scope['from'] ?: '1970-01-01' ) . '...' . ( $scope['to'] ?: wp_date( 'Y-m-d' ) ); }
        return array_map( 'intval', wc_get_orders( $args ) );
    }
    public function write( $entity, string $type, string $address, array $selection, bool $order_display ): void {
        if ( $type === 'customer' ) { ( new CustomerAddress() )->write( $entity, array( $address => $selection ) ); }
        elseif ( $order_display ) { ( new OrderAddress() )->write( $entity, array( $address => $selection ) ); }
        else {
            foreach ( array( 'province','ward' ) as $part ) { $entity->update_meta_data( AddressValidator::meta( $address, $part . '_code' ), $selection[ $part ]['code'] ); }
        }
        if ( ! $entity->save() ) { throw new \RuntimeException( 'save_failed' ); }
    }
}
