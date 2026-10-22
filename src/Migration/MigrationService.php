<?php
namespace Tunlamweb\VNAddressPro\Migration;
use Tunlamweb\VNAddressPro\Repository\LocalAddressRepository;
use Tunlamweb\VNAddressPro\Address\{AddressService,Normalizer,Validator};
use Tunlamweb\VNAddressPro\Support\{Search,Logger};
use Tunlamweb\VNAddressPro\Data\DatasetIntegrityAuditor;
use Tunlamweb\VNAddressPro\Sync\DatasetSyncLock;
use Tunlamweb\VNAddressPro\Integrations\WooCommerce\Compatibility;
defined( 'ABSPATH' ) || exit;

final class MigrationService {
    public const MAX_BATCH = 50;
    public static function allowed(): bool { return Compatibility::supported() && current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ); }
    public static function dataset_identity(): string {
        return hash( 'sha256', wp_json_encode( array( get_option( 'vnap_dataset_version' ), get_option( 'vnap_dataset_metadata' ), get_option( 'vnap_db_version' ) ) ) );
    }
    public function scope( array $raw ): array|\WP_Error {
        $scope = array( 'mode' => 'customers', 'addresses' => 'both', 'batch' => 25, 'page' => 1, 'from' => '', 'to' => '', 'include' => array(), 'order_display' => false );
        foreach ( array( 'mode','addresses','from','to' ) as $key ) { if ( isset( $raw[ $key ] ) ) { if ( ! is_string( $raw[ $key ] ) ) { return $this->error( 'scope' ); } $scope[ $key ] = $raw[ $key ]; } }
        if ( ! in_array( $scope['mode'], array( 'customers','orders','both' ), true ) || ! in_array( $scope['addresses'], array( 'billing','shipping','both' ), true ) ) { return $this->error( 'scope' ); }
        foreach ( array( 'batch','page' ) as $key ) {
            if ( isset( $raw[ $key ] ) ) { if ( NameMatcher::code( $raw[ $key ] ) === null ) { return $this->error( 'scope' ); } $scope[ $key ] = (int) $raw[ $key ]; }
        }
        if ( $scope['batch'] > self::MAX_BATCH || $scope['page'] > 1000000 ) { return $this->error( 'scope' ); }
        if ( $scope['mode'] === 'both' && $scope['batch'] < 2 ) { return $this->error( 'scope' ); }
        foreach ( array( 'from','to' ) as $key ) {
            if ( $scope[ $key ] !== '' ) { $d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $scope[ $key ], wp_timezone() ); if ( ! $d || $d->format( 'Y-m-d' ) !== $scope[ $key ] ) { return $this->error( 'dates' ); } }
        }
        if ( ! empty( $raw['days'] ) ) {
            $days = NameMatcher::code( $raw['days'] ); if ( $days === null || $days > 36500 ) { return $this->error( 'dates' ); }
            $today = new \DateTimeImmutable( 'today', wp_timezone() );
            $scope['from'] = $today->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' ); $scope['to'] = $today->format( 'Y-m-d' );
        }
        if ( $scope['from'] && $scope['to'] && $scope['from'] > $scope['to'] ) { return $this->error( 'dates' ); }
        if ( isset( $raw['include'] ) ) {
            if ( ! is_array( $raw['include'] ) || count( $raw['include'] ) > 100 ) { return $this->error( 'scope' ); }
            foreach ( $raw['include'] as $id ) { if ( NameMatcher::code( $id ) === null ) { return $this->error( 'scope' ); } $scope['include'][] = (int) $id; }
            $scope['include'] = array_values( array_unique( $scope['include'] ) );
        }
        $scope['order_display'] = ( $raw['order_display'] ?? false ) === true;
        return $scope;
    }
    public function scan( array $raw = array() ): array|\WP_Error {
        if ( ! self::allowed() ) { return $this->error( 'permission' ); }
        $scope = $this->scope( $raw ); if ( is_wp_error( $scope ) ) { return $scope; }
        $identity = self::dataset_identity();
        if ( ( new DatasetSyncLock() )->active() || ! ( new DatasetIntegrityAuditor() )->audit()['healthy'] ) { return $this->error( 'dataset_unhealthy' ); }
        $source = new WooAddressSource(); $analyzer = new LegacyAddressAnalyzer( new LocalAddressRepository() );
        $types = $scope['mode'] === 'both' ? array( 'customer','order' ) : array( $scope['mode'] === 'customers' ? 'customer' : 'order' );
        $limit = max( 1, (int) floor( $scope['batch'] / count( $types ) ) ); $items = array(); $more = false;
        foreach ( $types as $type ) {
            $ids = $source->ids( $type, $scope, $limit ); $more = $more || count( $ids ) > $limit;
            foreach ( array_slice( $ids, 0, $limit ) as $id ) {
                $entity = $source->load( $type, $id ); if ( ! $entity ) { continue; }
                foreach ( $scope['addresses'] === 'both' ? array( 'billing','shipping' ) : array( $scope['addresses'] ) as $address ) {
                    $s = $source->snapshot( $entity, $type, $address ); $r = $analyzer->analyze( $type, $id, $address, $s, $scope['order_display'] )->to_array();
                    $r['fingerprint'] = WooAddressSource::fingerprint( $s ); $items[] = $r;
                }
            }
        }
        if ( $identity !== self::dataset_identity() || ( new DatasetSyncLock() )->active() ) { return $this->error( 'dataset_changed' ); }
        $plan = array( 'version' => PlanCodec::VERSION, 'id' => wp_generate_uuid4(), 'user' => get_current_user_id(), 'site' => get_current_blog_id(), 'expires' => time() + PlanCodec::TTL,
            'dataset' => $identity, 'dataset_version' => get_option( 'vnap_dataset_version' ), 'scope' => $scope, 'items' => $items, 'more' => $more, 'dry_run' => true );
        $token = ( new PlanCodec() )->encode( $plan ); if ( is_wp_error( $token ) ) { return $token; }
        ( new Logger() )->event( 'migration_scan_complete', array( 'resource' => $plan['id'], 'status' => count( $items ) ) );
        return array( 'plan' => $plan, 'token' => $token );
    }
    public function apply( string $token, array $selected, bool $confirm = false, bool $orders = false, bool $medium = false ): array|\WP_Error {
        if ( ! self::allowed() ) { return $this->error( 'permission' ); }
        $plan = ( new PlanCodec() )->decode( $token ); if ( is_wp_error( $plan ) ) { return $plan; }
        if ( ! $confirm || ! $selected || count( $selected ) > 100 ) { return $this->error( 'confirmation' ); }
        $indices = array();
        foreach ( $selected as $i ) {
            if ( ! ( is_int( $i ) || is_string( $i ) ) || ! preg_match( '/\A(?:0|[1-9][0-9]*)\z/', (string) $i ) || ! isset( $plan['items'][ (int) $i ] ) ) { return $this->error( 'selection' ); }
            $indices[] = (int) $i;
            if ( $plan['items'][ (int) $i ]['source_type'] === 'order' && ! $orders ) { return $this->error( 'order_confirmation' ); }
        }
        $indices = array_values( array_unique( $indices ) );
        if ( $plan['dataset'] !== self::dataset_identity() ) { return $this->error( 'dataset_changed' ); }
        $lock = new DatasetSyncLock(); $lease = $lock->acquire(); if ( $lease === null ) { return $this->error( 'busy' ); }
        $audit = new MigrationAudit(); $logger = new Logger();
        try {
            if ( $plan['dataset'] !== self::dataset_identity() || ! ( new DatasetIntegrityAuditor() )->audit()['healthy'] ) { return $this->error( 'dataset_unhealthy' ); }
            $existing = $audit->read( $plan['id'] ); if ( $existing ) { return $existing + array( 'replayed' => true ); }
            $run = array( 'id' => $plan['id'], 'at' => time(), 'user' => get_current_user_id(), 'scope' => $plan['scope'], 'dataset_version' => $plan['dataset_version'], 'dataset' => $plan['dataset'], 'matcher_version' => PlanCodec::VERSION,
                'dry_run' => false, 'scanned' => count( $plan['items'] ), 'proposed' => count( array_filter( $plan['items'], static fn( $i ) => (bool) $i['proposed_changes'] ) ), 'applied' => 0, 'skipped' => 0, 'failed' => 0, 'items' => array() );
            foreach ( $indices as $i ) {
                $r = $plan['items'][ $i ];
                $run['items'][ $i ] = array( 'source_type' => $r['source_type'], 'entity_id' => $r['entity_id'], 'address_type' => $r['address_type'], 'before' => $r['original'], 'after' => $r['target'], 'confidence' => $r['overall_confidence'], 'status' => 'PREPARED' );
            }
            if ( ! $audit->begin( $run ) ) { return $this->error( 'audit_claim' ); }
            $logger->event( 'migration_apply_start', array( 'resource' => $run['id'], 'status' => count( $indices ) ) );
            $local = new LocalAddressRepository(); $validator = new AddressService( $local, new Normalizer(), new Validator(), new Search() ); $analyzer = new LegacyAddressAnalyzer( $local ); $source = new WooAddressSource();
            foreach ( $indices as $i ) {
                $r = $plan['items'][ $i ]; $status = 'FAILED_SAVE';
                try {
                    $entity = $source->load( $r['source_type'], $r['entity_id'] );
                    $can = $r['source_type'] === 'order' ? current_user_can( 'edit_shop_order', $r['entity_id'] ) : current_user_can( 'edit_user', $r['entity_id'] );
                    if ( ! $entity || ! $can ) { $status = 'FAILED_VALIDATION'; }
                    elseif ( $r['fingerprint'] !== WooAddressSource::fingerprint( $source->snapshot( $entity, $r['source_type'], $r['address_type'] ) ) ) { $status = 'SKIPPED_STALE'; }
                    elseif ( ! $r['proposed_changes'] ) { $status = 'UNCHANGED'; }
                    elseif ( ! in_array( $r['status'], array( 'MIGRATABLE_HIGH','MIGRATABLE_MEDIUM' ), true ) || ! in_array( $r['overall_confidence'], $medium ? array( 'HIGH','MEDIUM' ) : array( 'HIGH' ), true ) ) { $status = 'SKIPPED_CONFIDENCE'; }
                    else {
                        $fresh = $analyzer->analyze( $r['source_type'], $r['entity_id'], $r['address_type'], $r['original'], $plan['scope']['order_display'] )->to_array();
                        $selection = $validator->validate_selection( $r['province_candidate']['candidate']['code'], $r['ward_candidate']['candidate']['code'] );
                        if ( is_wp_error( $selection ) || $fresh['target'] !== $r['target'] || $fresh['status'] !== $r['status'] || ! $lock->owns( $lease ) || $plan['dataset'] !== self::dataset_identity() ) { $status = 'FAILED_VALIDATION'; }
                        else {
                            $source->write( $entity, $r['source_type'], $r['address_type'], $selection, $plan['scope']['order_display'] );
                            $saved = $source->load( $r['source_type'], $r['entity_id'] );
                            $status = $saved && WooAddressSource::fingerprint( $source->snapshot( $saved, $r['source_type'], $r['address_type'] ) ) === WooAddressSource::fingerprint( $r['target'] ) ? 'APPLIED' : 'FAILED_SAVE';
                        }
                    }
                } catch ( \Throwable $error ) { $status = 'FAILED_SAVE'; }
                $run['items'][ $i ]['status'] = $status;
                if ( $status === 'APPLIED' ) { ++$run['applied']; }
                elseif ( str_starts_with( $status, 'FAILED' ) ) { ++$run['failed']; }
                else { ++$run['skipped']; }
                if ( $status === 'SKIPPED_STALE' ) { $logger->event( 'migration_stale_record', array( 'resource' => $run['id'] ) ); }
                if ( ! $audit->save( $run ) ) { return $this->error( 'audit_write' ); }
            }
            $logger->event( 'migration_apply_complete', array( 'resource' => $run['id'], 'status' => $run['applied'] ) );
            return $run;
        } finally { $lock->release( $lease ); }
    }
    private function error( string $code ): \WP_Error {
        return new \WP_Error( 'vnap_migration_' . $code, __( 'Migration cannot continue. Check permission, confirmation and selection; repair the local dataset if needed, then scan again.', 'vn-address-pro' ) );
    }
}
