<?php
/** Run with wp eval-file on a disposable WordPress installation. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

use Tunlamweb\VNAddressPro\Plugin;
use Tunlamweb\VNAddressPro\Support\Requirements;

$vnap_check = static function ( $condition, $message ) {
    if ( ! $condition ) {
        WP_CLI::error( $message );
    }
};
$vnap_check( Requirements::met(), 'Requirements failed on supported runtime.' );
$vnap_check( VNAP_VERSION === get_file_data( VNAP_FILE, array( 'version' => 'Version' ) )['version'], 'Header/version mismatch.' );
$vnap_check( get_option( 'vnap_version' ) === VNAP_VERSION, 'Installed version marker missing.' );
$vnap_check( class_exists( 'VN_Address_API_2025', false ), 'Legacy class unavailable.' );
foreach ( array( 'init', 'register_routes', 'get_provinces', 'get_wards', 'get_province' ) as $method ) {
    $vnap_check( is_callable( array( 'VN_Address_API_2025', $method ) ), 'Legacy public method missing: ' . $method );
}
$instance = Plugin::instance();
$instance->boot();
\Tunlamweb\VNAddressPro\Compatibility\Legacy::load();
include VNAP_FILE;
$vnap_check( Plugin::instance() === $instance, 'Plugin instance duplicated.' );
$vnap_check( did_action( 'vnap_loaded' ) === 1, 'vnap_loaded must fire exactly once.' );
$vnap_check( has_action( 'rest_api_init', array( 'VN_Address_API_2025', 'register_routes' ) ) !== false, 'REST registration hook missing.' );

$writes = 0;
$counter = static function ( $old, $new ) use ( &$writes ) { ++$writes; };
add_action( 'update_option_vnap_version', $counter, 10, 2 );
$instance->maybe_upgrade();
$vnap_check( $writes === 0, 'Same-version request wrote version option.' );
update_option( 'vnap_version', '1.0.0' );
$instance->maybe_upgrade();
$vnap_check( get_option( 'vnap_version' ) === VNAP_VERSION, 'Upgrade marker was not advanced.' );
update_option( 'vnap_version', '999.0.0' );
$instance->maybe_upgrade();
$vnap_check( get_option( 'vnap_version' ) === '999.0.0', 'Newer installed marker was downgraded.' );
update_option( 'vnap_version', VNAP_VERSION );
remove_action( 'update_option_vnap_version', $counter, 10 );

$saved = get_transient( 'vn_address_provinces_v2' );
set_transient( 'vn_address_provinces_v2', array( 'sentinel' => true ), DAY_IN_SECONDS );
$cron = _get_cron_array();
$http = 0;
$deny = static function ( $pre ) use ( &$http ) { ++$http; return new WP_Error( 'unexpected_http', 'Unexpected lifecycle HTTP' ); };
add_filter( 'pre_http_request', $deny );
Plugin::deactivate();
Plugin::activate();
remove_filter( 'pre_http_request', $deny );
$vnap_check( $http === 0, 'Lifecycle made an outbound request.' );
$vnap_check( $cron === _get_cron_array(), 'Lifecycle changed cron events.' );
$vnap_check( get_transient( 'vn_address_provinces_v2' ) === array( 'sentinel' => true ), 'Lifecycle removed existing cache.' );
delete_transient( 'vn_address_provinces_v2' );
if ( $saved !== false ) {
    set_transient( 'vn_address_provinces_v2', $saved, DAY_IN_SECONDS );
}
$metadata = get_file_data( VNAP_FILE, array( 'name' => 'Plugin Name', 'author' => 'Author', 'domain' => 'Text Domain' ) );
$vnap_check( $metadata === array( 'name' => 'VN Address Pro', 'author' => 'Tunlamweb', 'domain' => 'vn-address-pro' ), 'Plugin metadata mismatch.' );
$vnap_check( has_action( 'activate_' . VNAP_BASENAME, array( Plugin::class, 'activate' ) ) !== false, 'Activation hook missing.' );
$vnap_check( has_action( 'deactivate_' . VNAP_BASENAME, array( Plugin::class, 'deactivate' ) ) !== false, 'Deactivation hook missing.' );
WP_CLI::success( 'Foundation: metadata, single boot, legacy methods, version lifecycle, cache and cron preservation passed.' );
