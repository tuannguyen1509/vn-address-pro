<?php
/**
 * Plugin Name: VN Address Pro
 * Description: Vietnamese province and ward address infrastructure for WordPress.
 * Version: 2.0.0
 * Author: Tunlamweb
 * Author URI: https://tunlamweb.com
 * Text Domain: vn-address-pro
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.3
 */

defined( 'ABSPATH' ) || exit;

// Keep the entrypoint parseable on older PHP; load core only after requirements pass.
if ( defined( 'VNAP_BOOTSTRAP_STARTED' ) ) {
    return;
}
define( 'VNAP_BOOTSTRAP_STARTED', true );

// The header is the single source of truth for release and requirement versions.
$vnap_headers = get_file_data( __FILE__, array(
    'version' => 'Version', 'php' => 'Requires PHP', 'wp' => 'Requires at least',
) );
$vnap_constants = array(
    'VNAP_VERSION' => $vnap_headers['version'],
    'VNAP_MIN_PHP' => $vnap_headers['php'],
    'VNAP_MIN_WP' => $vnap_headers['wp'],
    'VNAP_FILE' => __FILE__,
    'VNAP_PATH' => plugin_dir_path( __FILE__ ),
    'VNAP_URL' => plugin_dir_url( __FILE__ ),
    'VNAP_BASENAME' => plugin_basename( __FILE__ ),
);
foreach ( $vnap_constants as $vnap_name => $vnap_value ) {
    if ( ! defined( $vnap_name ) ) {
        define( $vnap_name, $vnap_value );
    }
}
unset( $vnap_headers, $vnap_constants, $vnap_name, $vnap_value );

require_once __DIR__ . '/src/Support/Requirements.php';
if ( ! \Tunlamweb\VNAddressPro\Support\Requirements::met() ) {
    add_action( 'admin_notices', array( 'Tunlamweb\VNAddressPro\Support\Requirements', 'notice' ) );
    add_action( 'network_admin_notices', array( 'Tunlamweb\VNAddressPro\Support\Requirements', 'notice' ) );
    register_activation_hook( __FILE__, array( 'Tunlamweb\VNAddressPro\Support\Requirements', 'reject_activation' ) );
    return;
}

try {
    require_once __DIR__ . '/includes/functions.php';
    spl_autoload_register( 'vnap_autoload' );
    if ( ! defined( 'VNAP_DB_VERSION' ) ) {
        define( 'VNAP_DB_VERSION', \Tunlamweb\VNAddressPro\Database\SchemaManager::VERSION );
    }
    register_activation_hook( __FILE__, array( 'Tunlamweb\VNAddressPro\Plugin', 'activate' ) );
    register_deactivation_hook( __FILE__, array( 'Tunlamweb\VNAddressPro\Plugin', 'deactivate' ) );
    \Tunlamweb\VNAddressPro\Plugin::instance()->boot();
} catch ( \Throwable $vnap_error ) {
    // Do not expose exception details, filesystem paths or stack traces to visitors.
    add_action( 'admin_notices', array( 'Tunlamweb\VNAddressPro\Support\Requirements', 'bootstrap_notice' ) );
    add_action( 'network_admin_notices', array( 'Tunlamweb\VNAddressPro\Support\Requirements', 'bootstrap_notice' ) );
}
