<?php
namespace Tunlamweb\VNAddressPro\Migration;

use Tunlamweb\VNAddressPro\Data\DatasetIntegrityAuditor;
use Tunlamweb\VNAddressPro\Repository\LocalAddressRepository;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\States;
use Tunlamweb\VNAddressPro\Sync\DatasetSyncLock;

defined( 'ABSPATH' ) || exit;

/** Explicit configuration conversion only. No activation hook or merchant-specific mappings. */
final class ShippingConfiguration {
    public const HISTORY = 'vnap_shipping_config_history';

    /** Compact names are suggestions, never evidence of equivalent historical boundaries. */
    public function target( string $value, array $provinces ): ?string {
        if ( ! str_starts_with( $value, 'VN:' ) ) { return null; }
        $raw = substr( $value, 3 );
        if ( States::from_wc_state_code( $raw ) !== null || ! preg_match( '/\A[A-Z]+\z/', $raw ) ) { return null; }
        $normalizer = new AddressNormalizer(); $matches = array();
        foreach ( $provinces as $province ) {
            $key = strtoupper( str_replace( ' ', '', $normalizer->normalize( $province['name'], 'province' ) ) );
            if ( $key === $raw ) { $matches[] = 'VN:' . States::to_wc_state_code( (int) $province['code'] ); }
        }
        return count( $matches ) === 1 ? $matches[0] : null;
    }

    public function scan(): array|\WP_Error {
        global $wpdb;
        if ( ! MigrationService::allowed() ) { return $this->error( 'permission' ); }
        $identity = MigrationService::dataset_identity();
        if ( ( new DatasetSyncLock() )->active() || ! ( new DatasetIntegrityAuditor() )->audit()['healthy'] ) { return $this->error( 'dataset' ); }
        $provinces = ( new LocalAddressRepository() )->get_provinces();
        if ( is_wp_error( $provinces ) ) { return $provinces; }
        // Bounded scan. Refuse oversized previews rather than silently omitting configuration.
        $rows = $wpdb->get_results( "SELECT l.location_id,l.zone_id,l.location_code,l.location_type,z.zone_name,z.zone_order FROM {$wpdb->prefix}woocommerce_shipping_zone_locations l INNER JOIN {$wpdb->prefix}woocommerce_shipping_zones z ON z.zone_id=l.zone_id WHERE l.location_type='state' AND l.location_code LIKE 'VN:%' ORDER BY l.location_id LIMIT 101", ARRAY_A );
        if ( $wpdb->last_error || count( $rows ?? array() ) > 99 ) { return $this->error( 'size_or_read' ); }
        $items = array(); $contexts = array();
        foreach ( $rows ?? array() as $row ) {
            if ( States::from_wc_state_code( substr( $row['location_code'], 3 ) ) !== null ) { continue; }
            $id = (int) $row['zone_id'];
            if ( ! isset( $contexts[ $id ] ) ) { $contexts[ $id ] = $this->zone_context( $id ); }
            if ( is_wp_error( $contexts[ $id ] ) ) { return $contexts[ $id ]; }
            $items[] = array( 'kind' => 'zone', 'label' => $row['zone_name'], 'source' => $row, 'zone_context' => $contexts[ $id ], 'before' => $row['location_code'], 'after' => $this->target( $row['location_code'], $provinces ) );
        }
        $store = get_option( 'woocommerce_default_country', '' );
        if ( is_string( $store ) && str_starts_with( $store, 'VN:' ) && States::from_wc_state_code( substr( $store, 3 ) ) === null ) {
            $items[] = array( 'kind' => 'store', 'label' => __( 'Store province', 'vn-address-pro' ), 'before' => $store, 'after' => $this->target( $store, $provinces ) );
        }
        if ( $identity !== MigrationService::dataset_identity() || ( new DatasetSyncLock() )->active() ) { return $this->error( 'dataset_changed' ); }
        $plan = array( 'version' => PlanCodec::VERSION, 'id' => wp_generate_uuid4(), 'user' => get_current_user_id(), 'site' => get_current_blog_id(), 'expires' => time() + PlanCodec::TTL,
            'purpose' => 'shipping_configuration_v1', 'dataset' => $identity, 'items' => $items );
        $token = ( new PlanCodec() )->encode( $plan );
        return is_wp_error( $token ) ? $token : array( 'plan' => $plan, 'token' => $token );
    }

    /** Atomic selected changes + bounded journal, guarded by a signed preview and fresh source. */
    public function apply( string $token, array $selected, bool $confirm ): array|\WP_Error {
        global $wpdb;
        if ( ! MigrationService::allowed() || ! $confirm || ! $selected || count( $selected ) > 100 ) { return $this->error( 'confirmation_or_permission' ); }
        $plan = ( new PlanCodec() )->decode( $token );
        if ( is_wp_error( $plan ) ) { return $plan; }
        if ( ( $plan['purpose'] ?? '' ) !== 'shipping_configuration_v1' ) { return $this->error( 'purpose' ); }
        $indices = array();
        foreach ( $selected as $index ) {
            if ( ! ( is_int( $index ) || is_string( $index ) ) || ! preg_match( '/\A(?:0|[1-9][0-9]*)\z/', (string) $index ) || ! isset( $plan['items'][ (int) $index ] ) || ! is_string( $plan['items'][ (int) $index ]['after'] ?? null ) ) { return $this->error( 'selection' ); }
            $indices[] = (int) $index;
        }
        $indices = array_values( array_unique( $indices ) ); sort( $indices );
        $lock = new DatasetSyncLock(); $lease = $lock->acquire();
        if ( $lease === null ) { return $this->error( 'busy' ); }
        $transaction = false;
        try {
            foreach ( array( $wpdb->options, $wpdb->prefix . 'woocommerce_shipping_zone_locations', $wpdb->prefix . 'woocommerce_shipping_zones' ) as $table ) {
                $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
                if ( strtoupper( (string) $engine ) !== 'INNODB' ) { return $this->error( 'transaction_engine' ); }
            }
            if ( $plan['dataset'] !== MigrationService::dataset_identity() || ! ( new DatasetIntegrityAuditor() )->audit()['healthy'] ) { return $this->error( 'dataset_changed' ); }
            $provinces = ( new LocalAddressRepository() )->get_provinces();
            if ( is_wp_error( $provinces ) ) { return $provinces; }
            $this->query( 'START TRANSACTION' ); $transaction = true;
            if ( ! $lock->owns( $lease ) ) { throw new \RuntimeException( 'lease' ); }
            $history = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", self::HISTORY ) ) );
            $history = is_array( $history ) ? $history : array();
            foreach ( $history as $run ) {
                if ( $run['id'] === $plan['id'] ) { $this->query( 'COMMIT' ); $transaction = false; return $run + array( 'replayed' => true ); }
            }
            $changes = array(); $validated_zones = array();
            foreach ( $indices as $index ) {
                $item = $plan['items'][ $index ];
                if ( $this->target( $item['before'], $provinces ) !== $item['after'] ) { throw new \RuntimeException( 'mapping_changed' ); }
                if ( $item['kind'] === 'zone' ) {
                    $source = $item['source'];
                    $zone_id = (int) $source['zone_id'];
                    if ( ! isset( $validated_zones[ $zone_id ] ) ) {
                        if ( $this->zone_context( $zone_id, true ) !== $item['zone_context'] ) { throw new \RuntimeException( 'zone_changed' ); }
                        $validated_zones[ $zone_id ] = true;
                    }
                    $current = $wpdb->get_row( $wpdb->prepare( "SELECT l.location_id,l.zone_id,l.location_code,l.location_type,z.zone_name,z.zone_order FROM {$wpdb->prefix}woocommerce_shipping_zone_locations l INNER JOIN {$wpdb->prefix}woocommerce_shipping_zones z ON z.zone_id=l.zone_id WHERE l.location_id=%d FOR UPDATE", $source['location_id'] ), ARRAY_A );
                    if ( $current !== $source ) { throw new \RuntimeException( 'stale' ); }
                    $sql = $wpdb->prepare( "UPDATE {$wpdb->prefix}woocommerce_shipping_zone_locations SET location_code=%s WHERE location_id=%d AND location_code=%s AND location_type='state'", $item['after'], $source['location_id'], $item['before'] );
                } elseif ( $item['kind'] === 'store' ) {
                    $current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", 'woocommerce_default_country' ) );
                    if ( $current !== $item['before'] ) { throw new \RuntimeException( 'stale' ); }
                    $sql = $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", $item['after'], 'woocommerce_default_country', $item['before'] );
                } else { throw new \RuntimeException( 'kind' ); }
                if ( $wpdb->query( $sql ) !== 1 ) { throw new \RuntimeException( 'write' ); }
                $changes[] = $item;
            }
            if ( ! $lock->owns( $lease ) || $plan['dataset'] !== MigrationService::dataset_identity() ) { throw new \RuntimeException( 'dataset_changed' ); }
            $run = array( 'id' => $plan['id'], 'at' => time(), 'user' => get_current_user_id(), 'applied' => count( $changes ), 'items' => $changes );
            array_unshift( $history, $run );
            $this->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload='no'", self::HISTORY, maybe_serialize( array_slice( $history, 0, 20 ) ) ) );
            $this->query( 'COMMIT' ); $transaction = false;
            return $run;
        } catch ( \Throwable $error ) {
            if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return $this->error( 'stale_or_write' );
        } finally {
            foreach ( array( self::HISTORY, 'woocommerce_default_country', 'alloptions', 'notoptions' ) as $key ) { wp_cache_delete( $key, 'options' ); }
            \WC_Cache_Helper::get_transient_version( 'shipping', true );
            \WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
            $lock->release( $lease );
        }
    }

    private function query( string $sql ): void {
        global $wpdb;
        if ( $wpdb->query( $sql ) === false ) { throw new \RuntimeException( 'database' ); }
    }
    private function zone_context( int $id, bool $lock = false ): string|\WP_Error {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT location_id,zone_id,location_code,location_type FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE zone_id=%d ORDER BY location_id LIMIT 1001" . ( $lock ? ' FOR UPDATE' : '' ), $id ), ARRAY_A );
        return $wpdb->last_error || count( $rows ?? array() ) > 1000 ? $this->error( 'zone_size_or_read' ) : hash( 'sha256', wp_json_encode( $rows ) );
    }
    private function error( string $reason ): \WP_Error {
        return new \WP_Error( 'vnap_shipping_' . $reason, __( 'Configuration conversion could not proceed. No partial batch is saved. Check permissions, dataset and selections, then scan again.', 'vn-address-pro' ) );
    }
}
