<?php
/** Disposable database only: replaces local rows and exercises intentional SQL failures. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }

use Tunlamweb\VNAddressPro\Contracts\DatasetProviderInterface;
use Tunlamweb\VNAddressPro\Data\{DatasetPackage,DatasetLoader,DatasetInstaller,DatasetIntegrityAuditor,DatasetMetadata,BundledDataset};
use Tunlamweb\VNAddressPro\Sync\{DatasetSyncService,DatasetSyncResult,DatasetSyncLock,DatasetScheduler,RemoteCacheCleaner};
use Tunlamweb\VNAddressPro\Database\TableNames;
use Tunlamweb\VNAddressPro\Providers\{ProvinceOpenApiDatasetProvider,ProvinceOpenApiProvider};
use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Admin\SettingsPage;

final class VnapSyncTestProvider implements DatasetProviderInterface {
    public mixed $response;
    public int $calls = 0;
    public ?Closure $before_fetch = null;
    public function fetch_dataset(): array|WP_Error { ++$this->calls; if ( $this->before_fetch ) { ( $this->before_fetch )(); } if ( $this->response instanceof Throwable ) { throw $this->response; } return $this->response; }
    public function get_remote_version(): array|WP_Error { $p = $this->fetch_dataset(); return is_wp_error( $p ) ? $p : $p['manifest']; }
}

global $wpdb;
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; WP_CLI::line( 'PASS ' . $message ); };
$bundle = ( new DatasetLoader() )->load();
if ( is_wp_error( $bundle ) ) { WP_CLI::error( 'Bundle unreadable.' ); }
$fixture = $bundle['dataset']; $fixture['provinces'] = array_slice( $fixture['provinces'], 0, 2 ); $fixture['dataset_version'] = '20200101000000';
$base = DatasetPackage::build( $fixture, 'Sync test' );
$installer = new DatasetInstaller(); $auditor = new DatasetIntegrityAuditor(); $provider = new VnapSyncTestProvider(); $sync = new DatasetSyncService( $provider );
$p = TableNames::provinces(); $w = TableNames::wards();
$saved = array();
foreach ( array( DatasetMetadata::SYNC,DatasetMetadata::AUDIT,DatasetMetadata::SCHEDULE ) as $key ) { $saved[ $key ] = get_option( $key, null ); }
$original_user = get_current_user_id(); $http = 0; $http_response = new WP_Error( 'offline', 'offline' );
$deny = static function ( $pre ) use ( &$http, &$http_response ) { ++$http; return $http_response; };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
add_filter( 'vnap_log_to_error_log', '__return_false' );
$old_handler = set_error_handler( static function ( $severity, $message, $file, $line ) { if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false; } );
$reset = static function () use ( $installer, $base, $provider ) { $provider->response = $base; $r = $installer->install_package( $base, true ); if ( is_wp_error( $r ) ) { throw new RuntimeException( 'Fixture reset failed' ); } };
try {
    $reset();
    $assert( $auditor->audit()['healthy'], 'Audit: healthy snapshot and content checksum' );
    $new = $fixture; $new['dataset_version'] = '20210101000000'; $new['provinces'][0]['name'] .= ' updated'; $candidate = DatasetPackage::build( $new, 'Sync test' );
    $provider->response = $candidate;
    $assert( $sync->check()->status === DatasetSyncResult::UPDATE_AVAILABLE && get_option( DatasetMetadata::VERSION ) === $fixture['dataset_version'], 'Check: newer candidate, no data publication' );
    $updated = array(); $hook = static function ( $old, $new ) use ( &$updated ) { $updated[] = array( $old, $new ); };
    add_action( 'vnap_dataset_updated', $hook, 10, 2 );
    $assert( $sync->sync()->status === DatasetSyncResult::SUCCESS && get_option( DatasetMetadata::VERSION ) === $new['dataset_version'] && $auditor->audit()['healthy'], 'Sync: newer candidate publishes verified complete rows' );
    $assert( $updated === array( array( $fixture['dataset_version'], $new['dataset_version'] ) ), 'Hook: updated versions only after publish' );
    remove_action( 'vnap_dataset_updated', $hook );
    $writes = 0; $watch = static function ( $sql ) use ( &$writes, $p, $w ) { if ( preg_match( '/^(INSERT INTO|DELETE FROM) (' . preg_quote( $p, '/' ) . '|' . preg_quote( $w, '/' ) . ')\b/', $sql ) ) { ++$writes; } return $sql; };
    add_filter( 'query', $watch );
    $assert( $sync->sync()->status === DatasetSyncResult::NO_UPDATE && $sync->check()->status === DatasetSyncResult::NO_UPDATE && $writes === 0, 'Same version/content: current, zero row rewrites' );
    remove_filter( 'query', $watch );
    $provider->response = $base;
    $assert( $sync->sync()->status === DatasetSyncResult::REMOTE_OLDER && $sync->check()->status === DatasetSyncResult::REMOTE_OLDER, 'Older remote: rejected for check and sync' );
    $conflict = $new; $conflict['provinces'][0]['name'] .= ' conflict'; $provider->response = DatasetPackage::build( $conflict, 'Sync test' );
    $assert( $sync->sync()->status === DatasetSyncResult::VERSION_CONFLICT && $auditor->audit()['healthy'], 'Same version/changed checksum: conflict, preserves local' );
    foreach ( array( 'schema','parent','missing collection','checksum' ) as $kind ) {
        $bad = $candidate;
        if ( $kind === 'schema' ) { $bad['dataset']['schema_version'] = 999; }
        elseif ( $kind === 'parent' ) { $bad['dataset']['provinces'][0]['wards'][0]['province_code'] = 999999; }
        elseif ( $kind === 'missing collection' ) { unset( $bad['dataset']['provinces'][0]['wards'] ); }
        else { $bad['manifest']['checksum'] = str_repeat( '0', 64 ); }
        $provider->response = $bad;
        $assert( $sync->sync()->status === DatasetSyncResult::VALIDATION_ERROR && get_option( DatasetMetadata::VERSION ) === $new['dataset_version'], 'Invalid remote ' . $kind . ' never publishes' );
    }
    $provider->response = new WP_Error( 'vnap_dataset_download' );
    $assert( $sync->sync()->status === DatasetSyncResult::DOWNLOAD_ERROR && $sync->check()->status === DatasetSyncResult::DOWNLOAD_ERROR, 'Remote timeout: controlled sync/check failure' );
    $provider->response = new RuntimeException( 'private detail must not be persisted' );
    $assert( $sync->sync()->status === DatasetSyncResult::IMPORT_ERROR && ! ( new DatasetSyncLock() )->active(), 'Unexpected provider exception releases lock without leaking text' );
    $lock = new DatasetSyncLock(); $lease = $lock->acquire();
    $assert( is_string( $lease ) && $lock->acquire() === null && $sync->sync()->status === DatasetSyncResult::LOCKED, 'Sync lease: acquire and double-click exclusion' );
    $lock->release( $lease );
    update_option( DatasetSyncLock::KEY, wp_json_encode( array( 'owner' => 'expired', 'expires' => time() - 1 ) ), false );
    $reclaimed = $lock->acquire();
    $assert( is_string( $reclaimed ), 'Sync lease: expired worker recoverable' );
    $lock->release( $lease );
    $assert( $lock->active(), 'Sync lease: old owner cannot release new worker' );
    $lock->release( $reclaimed );

    $reset(); $provider->response = $candidate;
    $provider->before_fetch = static function () { update_option( DatasetSyncLock::KEY, wp_json_encode( array( 'owner' => 'replacement-test-worker', 'expires' => time() + 600 ) ), false ); };
    $r = $sync->sync(); $provider->before_fetch = null;
    $assert( $r->status === DatasetSyncResult::IMPORT_ERROR && get_option( DatasetMetadata::VERSION ) === $fixture['dataset_version'] && $auditor->audit()['healthy'], 'Lost sync lease cannot publish stale worker candidate' );
    $assert( $lock->active(), 'Stale sync finally cannot release replacement worker lease' );
    delete_option( DatasetSyncLock::KEY );

    $reset(); $provider->response = $candidate;
    $fail = static fn( $sql ) => str_starts_with( $sql, "INSERT INTO $w " ) ? 'INVALID VNAP TEST SQL' : $sql;
    add_filter( 'query', $fail );
    try { $r = $sync->sync(); } finally { remove_filter( 'query', $fail ); }
    $assert( $r->status === DatasetSyncResult::IMPORT_ERROR && get_option( DatasetMetadata::VERSION ) === $fixture['dataset_version'] && $auditor->audit()['healthy'], 'Failed ward import rolls back provinces and metadata' );
    $corrupt = static function ( $sql ) use ( $p ) { return str_starts_with( $sql, "INSERT INTO $p " ) ? str_replace( ' updated', ' altered', $sql ) : $sql; };
    add_filter( 'query', $corrupt );
    try { $r = $sync->sync(); } finally { remove_filter( 'query', $corrupt ); }
    $assert( $r->status === DatasetSyncResult::VERIFY_ERROR && $auditor->audit()['healthy'] && get_option( DatasetMetadata::VERSION ) === $fixture['dataset_version'], 'Staged field corruption fails verification and rolls back' );
    $fail_commit = static fn( $sql ) => $sql === 'COMMIT' ? 'INVALID VNAP COMMIT' : $sql;
    add_filter( 'query', $fail_commit );
    try { $r = $sync->sync(); } finally { remove_filter( 'query', $fail_commit ); }
    $assert( $r->status === DatasetSyncResult::IMPORT_ERROR && $auditor->audit()['healthy'], 'Publish COMMIT failure preserves prior snapshot' );

    $before = rest_do_request( new WP_REST_Request( 'GET', '/vn-address/v1/provinces' ) )->get_data();
    $during = null; $reading = false;
    $read_during = static function ( $sql ) use ( $w, &$reading, &$during ) {
        global $wpdb;
        if ( ! $reading && str_starts_with( $sql, "INSERT INTO $w " ) ) {
            $reading = true; $writer = $wpdb;
            $reader = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $reader->set_prefix( $writer->prefix );
            try { $wpdb = $reader; $during = rest_do_request( new WP_REST_Request( 'GET', '/vn-address/v1/provinces' ) )->get_data(); }
            finally { $wpdb = $writer; $reader->close(); }
        }
        return $sql;
    };
    add_filter( 'query', $read_during );
    try { $r = $sync->sync(); } finally { remove_filter( 'query', $read_during ); }
    $assert( $r->status === DatasetSyncResult::SUCCESS && $during === $before, 'Concurrent REST connection sees old complete dataset during import' );
    $assert( ! $lock->active(), 'Successful sync releases lease' );
    $state = ( new DatasetMetadata() )->state();
    $assert( isset( $state['last_success'], $state['last_failure'], $state['last_attempt'] ) && count( $state ) <= 4, 'History bounded to last check/attempt/success/failure' );

    foreach ( array( $p, $w ) as $table ) {
        $wpdb->query( "RENAME TABLE $table TO {$table}_test_saved" );
        try { $assert( ! $auditor->audit()['healthy'], 'Audit detects missing ' . ( $table === $p ? 'province' : 'ward' ) . ' table' ); }
        finally { $wpdb->query( "RENAME TABLE {$table}_test_saved TO $table" ); }
    }
    $wpdb->query( "DELETE FROM $w" );
    $assert( ! $auditor->audit()['healthy'], 'Audit detects unexpectedly empty wards' );
    $reset(); $wpdb->query( "DELETE FROM $p" );
    $assert( in_array( 'orphan_wards', $auditor->audit()['issues'], true ), 'Audit detects orphan wards and empty provinces' );
    $reset(); delete_option( DatasetMetadata::DATA );
    $assert( in_array( 'metadata', $auditor->audit()['issues'], true ), 'Audit detects missing metadata' );
    $reset();
    $assert( $sync->repair()->status === DatasetSyncResult::HEALTHY, 'Repair: healthy data is not reinstalled' );
    $wpdb->query( "DELETE FROM $w" ); $provider->response = $base;
    $assert( $sync->repair()->status === DatasetSyncResult::SUCCESS && $auditor->audit()['healthy'], 'Repair: damaged data restored from same known remote candidate' );
    $wpdb->query( "DELETE FROM $w" ); $provider->response = new WP_Error( 'vnap_dataset_download' );
    $assert( $sync->repair()->status === DatasetSyncResult::SUCCESS && get_option( DatasetMetadata::VERSION ) === BundledDataset::VERSION, 'Repair: remote unavailable recovers from validated bundle' );
    $reset(); $provider->response = $candidate; $sync->sync(); $wpdb->query( "DELETE FROM $w" );
    $provider->response = new WP_Error( 'vnap_dataset_download' );
    // Make installed version newer than bundle without changing content identity.
    $future = $candidate; $future['dataset']['dataset_version'] = '20990101000000'; $future = DatasetPackage::build( $future['dataset'], 'Sync test' ); $installer->install_package( $future, true );
    $wpdb->query( "DELETE FROM $w" );
    $assert( $sync->repair()->status === DatasetSyncResult::DOWNGRADE_BLOCKED && $sync->reinstall()->status === DatasetSyncResult::DOWNGRADE_BLOCKED, 'Repair/reinstall never silently downgrade newer installed dataset' );
    $assert( $sync->reinstall( true )->status === DatasetSyncResult::SUCCESS && $auditor->audit()['healthy'], 'Explicit confirmed bundled downgrade recovers offline' );
    $provider->response = new WP_Error( 'vnap_dataset_download' );
    $wpdb->query( "DELETE FROM $w" );
    $bad_bundle_service = new DatasetSyncService( $provider, new DatasetLoader( get_temp_dir() . 'vnap_missing_' . wp_generate_uuid4() ) );
    $before_repair = $auditor->snapshot( BundledDataset::VERSION );
    $assert( $bad_bundle_service->repair()->status === DatasetSyncResult::VALIDATION_ERROR && $auditor->snapshot( BundledDataset::VERSION ) === $before_repair, 'Repair with unavailable/invalid bundle never destroys recoverable rows' );
    $provider->response = $bundle; add_filter( 'query', $fail );
    try { $r = $sync->repair(); } finally { remove_filter( 'query', $fail ); }
    $assert( $r->status === DatasetSyncResult::IMPORT_ERROR && $auditor->snapshot( BundledDataset::VERSION ) === $before_repair, 'Failed repair preserves previous recoverable state' );
    $installer->install( null, true );

    $adapter = new ProvinceOpenApiDatasetProvider();
    foreach ( array( 'timeout','invalid JSON','partial data','bad parent' ) as $kind ) {
        $http_response = new WP_Error( 'timeout' );
        if ( $kind !== 'timeout' ) {
            $raw = $bundle['dataset']['provinces'];
            if ( $kind === 'partial data' ) { unset( $raw[0]['wards'] ); }
            if ( $kind === 'bad parent' ) { $raw[0]['wards'][0]['province_code'] = 999999; }
            $http_response = array( 'response' => array( 'code' => 200 ), 'body' => $kind === 'invalid JSON' ? '{broken' : wp_json_encode( $raw ), 'headers' => array() );
        }
        $assert( is_wp_error( $adapter->fetch_dataset() ), 'Default adapter rejects ' . $kind );
    }
    $http_response = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $bundle['dataset']['provinces'] ), 'headers' => array() );
    $before_http = $http; $package = $adapter->fetch_dataset();
    $assert( ! is_wp_error( $package ) && DatasetPackage::valid( $package ) && $http - $before_http === 1, 'Default adapter: complete dataset in one bounded request' );
    $assert( $package['manifest']['dataset_version'] === BundledDataset::VERSION && ( new DatasetSyncService() )->sync()->status === DatasetSyncResult::NO_UPDATE, 'Default adapter: unchanged content retains version, no cron rewrite' );
    $raw = $bundle['dataset']['provinces']; foreach ( $raw as &$row ) { foreach ( $row['wards'] as &$ward ) { unset( $ward['province_code'] ); } unset( $ward ); } unset( $row );
    $http_response['body'] = wp_json_encode( $raw );
    $assert( ! is_wp_error( $adapter->fetch_dataset() ), 'Root adapter materializes missing nested parent context' );

    $scheduler = new DatasetScheduler();
    foreach ( array( 'daily','weekly','vnap_monthly','daily' ) as $frequency ) {
        update_option( DatasetMetadata::SCHEDULE, $frequency, false ); $scheduler->reconcile(); $scheduler->reconcile();
        $events = 0; foreach ( _get_cron_array() as $hooks ) { $events += count( $hooks[ DatasetScheduler::HOOK ] ?? array() ); }
        $assert( wp_get_scheduled_event( DatasetScheduler::HOOK )->schedule === $frequency && $events === 1, 'Cron frequency ' . $frequency . ': single event' );
    }
    $before_http = $http; $scheduler->run();
    $assert( $http - $before_http === 1 && get_option( DatasetMetadata::VERSION ) === BundledDataset::VERSION, 'Cron checks remote without rewriting unchanged snapshot' );
    Plugin::deactivate(); $assert( ! wp_next_scheduled( DatasetScheduler::HOOK ), 'Deactivate removes only scheduled dataset job' );
    Plugin::activate(); $assert( wp_get_scheduled_event( DatasetScheduler::HOOK )->schedule === 'daily', 'Reactivate restores configured schedule' );
    update_option( DatasetMetadata::SCHEDULE, 'disabled', false ); $scheduler->reconcile(); $before_http = $http; $scheduler->run();
    $assert( ! wp_next_scheduled( DatasetScheduler::HOOK ) && $http === $before_http, 'Disabled removes event and stops scheduled remote requests' );
    $http_response = new WP_Error( 'offline' ); update_option( DatasetMetadata::SCHEDULE, 'weekly', false ); $scheduler->run();
    $assert( ( new DatasetMetadata() )->state()['last_attempt']['status'] === DatasetSyncResult::DOWNLOAD_ERROR && $auditor->audit()['healthy'], 'Cron failure persists controlled status and preserves dataset' );
    update_option( DatasetMetadata::SCHEDULE, 'disabled', false );

    wp_set_current_user( 0 ); $page = new SettingsPage();
    $assert( ! $page->authorize( 'sync', wp_create_nonce( 'vnap_sync' ) ), 'Admin security: anonymous valid nonce is insufficient' );
    $admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]; wp_set_current_user( $admin->ID );
    $assert( ! $page->authorize( 'sync', 'invalid' ) && ! $page->authorize( 'sync', wp_create_nonce( 'vnap_repair' ) ) && $page->authorize( 'sync', wp_create_nonce( 'vnap_sync' ) ), 'Admin security: capability and action-specific nonce required' );
    $subscriber = wp_insert_user( array( 'user_login' => 'vnap_sync_subscriber', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
    wp_set_current_user( $subscriber );
    $assert( ! $page->authorize( 'sync', wp_create_nonce( 'vnap_sync' ) ), 'Admin security: lower capability user denied with own valid nonce' );
    wp_set_current_user( $admin->ID ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $subscriber );
    foreach ( array( 'general','data','tools','status' ) as $tab ) {
        $_GET['tab'] = $tab; $before_http = $http; ob_start(); $page->render(); $html = ob_get_clean();
        $assert( str_contains( $html, 'vnap-admin' ) && ! str_contains( $html, '<script' ) && $http === $before_http, 'Admin ' . $tab . ': renders without remote probes or scripts' );
    }
    unset( $_GET['tab'] );
    $keys = ProvinceOpenApiProvider::runtime_keys(); set_transient( $keys['active'], array( 'test' ), 600 ); set_transient( $keys['lkg'], array( 'test' ), 600 ); set_transient( $keys['negative'], true, 600 );
    ( new RemoteCacheCleaner() )->clear();
    $assert( get_transient( $keys['active'] ) === false && get_transient( $keys['negative'] ) === false && get_transient( $keys['lkg'] ) === array( 'test' ), 'Clear remote cache retains LKG' );
    delete_transient( $keys['lkg'] );
    $before_http = $http;
    foreach ( array( '/provinces','/wards?province_code=79','/province/79','/health' ) as $url ) {
        $parts = explode( '?', $url ); $request = new WP_REST_Request( 'GET', '/vn-address/v1' . $parts[0] );
        if ( isset( $parts[1] ) ) { $request->set_param( 'province_code', 79 ); }
        $assert( rest_do_request( $request )->get_status() === 200, 'Offline after failed sync: ' . $parts[0] );
    }
    $assert( $http === $before_http, 'Healthy frontend never triggers sync or remote lookup' );
    WP_CLI::success( $checks . ' dataset sync/admin checks passed.' );
} finally {
    restore_error_handler(); remove_filter( 'pre_http_request', $deny, PHP_INT_MAX ); remove_filter( 'vnap_log_to_error_log', '__return_false' );
    $installer->install( null, true ); delete_option( DatasetSyncLock::KEY );
    foreach ( $saved as $key => $value ) { if ( $value === null ) { delete_option( $key ); } else { update_option( $key, $value, false ); } }
    ( new DatasetScheduler() )->reconcile(); wp_set_current_user( $original_user );
}
