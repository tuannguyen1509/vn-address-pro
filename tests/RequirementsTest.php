<?php
/** Standalone early-bootstrap test; run with php, including on PHP 7.4. */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
    exit;
}
define( 'ABSPATH', __DIR__ . '/' );
$wp_version = isset( $argv[1] ) ? $argv[1] : '6.2';
$vnap_test_hooks = array();
function get_file_data( $file, $headers ) {
    $data = file_get_contents( $file );
    $result = array();
    foreach ( $headers as $key => $header ) {
        preg_match( '/^[ \t*]*' . preg_quote( $header, '/' ) . ':\s*(.+)$/m', $data, $matches );
        $result[ $key ] = trim( $matches[1] );
    }
    return $result;
}
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return '/plugins/'; }
function plugin_basename( $file ) { return 'vn-address-pro/' . basename( $file ); }
function add_action( $hook, $callback ) { $GLOBALS['vnap_test_hooks'][ $hook ] = $callback; }
function register_activation_hook( $file, $callback ) { $GLOBALS['vnap_test_hooks']['activate'] = $callback; }
function current_user_can( $capability ) { return true; }
function __( $message, $domain ) { return $message; }
function esc_html( $message ) { return htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $message, $domain ) { return esc_html( $message ); }
function wp_die( $message, $title, $options ) { throw new RuntimeException( $message ); }
set_error_handler( static function ( $severity, $message ) { throw new ErrorException( $message, 0, $severity ); } );
require dirname( __DIR__ ) . '/vn-address-api.php';
if ( class_exists( 'VN_Address_API_2025', false ) || class_exists( 'Tunlamweb\\VNAddressPro\\Plugin', false ) ) {
    throw new RuntimeException( 'Core was loaded on an unsupported runtime.' );
}
ob_start();
call_user_func( $vnap_test_hooks['admin_notices'] );
$notice = ob_get_clean();
if ( strpos( $notice, 'requires PHP 8.1' ) === false || strpos( $notice, 'WordPress 6.3' ) === false ) {
    throw new RuntimeException( 'Missing requirement notice.' );
}
$rejected = false;
try {
    call_user_func( $vnap_test_hooks['activate'] );
} catch ( RuntimeException $error ) {
    $rejected = strpos( $error->getMessage(), 'requires PHP' ) !== false;
}
if ( ! $rejected ) {
    throw new RuntimeException( 'Unsupported activation was not rejected.' );
}
echo "PASS: unsupported requirements stop core and show an actionable notice.\n";
