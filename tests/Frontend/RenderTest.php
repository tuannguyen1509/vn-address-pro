<?php
/** wp eval-file on a disposable site; requires the normal bundled local installation. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
use Tunlamweb\VNAddressPro\Frontend\{AddressComponent,Assets};
use Tunlamweb\VNAddressPro\Plugin;

$count = 0; $http = 0;
$assert = static function ( $condition, $message ) use ( &$count ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$count; WP_CLI::line( 'PASS ' . $message ); };
$block = static function () use ( &$http ) { ++$http; return new WP_Error( 'offline' ); };
add_filter( 'pre_http_request', $block, PHP_INT_MAX );
set_error_handler( static function ( $severity, $message, $file, $line ) { if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false; } );
try {
    $assert( ! wp_script_is( Assets::HANDLE, 'enqueued' ) && ! wp_style_is( Assets::HANDLE, 'enqueued' ), 'Assets not enqueued before rendering' );
    $html = do_shortcode( '[vn_address_fields]' );
    $assert( str_contains( $html, 'data-vnap-address' ) && str_contains( $html, 'name="vnap_province"' ) && str_contains( $html, 'name="vnap_ward"' ), 'Default shortcode renders code fields' );
    $assert( str_contains( $html, 'data-vnap-province-name' ) && str_contains( $html, 'data-vnap-ward-name' ), 'Hidden names exposed independently' );
    $assert( str_contains( $html, 'name="vnap_address"' ) && str_contains( $html, 'data-vnap-address-line' ), 'Optional address shown by default' );
    $assert( preg_match( '/<select[^>]*data-vnap-ward[^>]*disabled/', $html ) === 1, 'Ward starts disabled with placeholder only' );
    $assert( wp_script_is( Assets::HANDLE, 'enqueued' ) && wp_style_is( Assets::HANDLE, 'enqueued' ), 'Render enqueues script and CSS' );
    $assert( wp_scripts()->registered[ Assets::HANDLE ]->deps === array() && wp_scripts()->registered[ Assets::HANDLE ]->ver === VNAP_VERSION, 'No jQuery dependency; release cache version' );
    preg_match( '/=\s*(\{.*\});/s', wp_scripts()->get_data( Assets::HANDLE, 'data' ), $config_match );
    $localized = json_decode( $config_match[1] ?? '', true );
    $assert( ( $localized['wardsUrl'] ?? '' ) === rest_url( 'vn-address/v1/wards' ) && ! isset( $localized['nonce'] ), 'Config uses existing REST URL and no public GET nonce' );
    $assert( ! str_contains( $html, '<script' ) && strlen( $html ) < 30000, 'HTML contains no embedded full ward dataset' );
    $custom = AddressComponent::render( array( 'province_name' => 'customer[province]', 'ward_name' => 'customer[ward]', 'address_name' => 'customer[address]', 'province_label' => 'Province custom', 'ward_placeholder' => 'Ward custom', 'required' => 'yes' ) );
    $assert( str_contains( $custom, 'name="customer[province]"' ) && str_contains( $custom, 'name="customer[ward]"' ), 'Bracketed custom field names' );
    $assert( str_contains( $custom, 'Province custom' ) && str_contains( $custom, 'Ward custom' ), 'Custom labels/placeholders' );
    $assert( substr_count( $custom, ' required aria-required="true"' ) === 3, 'Required flags reach all visible fields' );
    $flags = AddressComponent::render( array( 'required' => true, 'ward_required' => false, 'show_address' => false ) );
    $assert( substr_count( $flags, ' required aria-required="true"' ) === 1 && ! str_contains( $flags, 'data-vnap-address-line' ), 'Boolean per-field required override and hidden street' );
    $ids = array(); preg_match_all( '/\bid="([^"]+)"/', $html . $custom, $ids );
    $assert( count( $ids[1] ) === count( array_unique( $ids[1] ) ), 'Multiple renders have unique IDs' );
    $assert( substr_count( $html, '<label ' ) === 3 && substr_count( $html, 'aria-describedby=' ) === 3 && str_contains( $html, 'role="alert"' ) && str_contains( $html, 'aria-live="polite"' ), 'Labels, descriptions and accessible status/error regions' );
    $escaped = AddressComponent::render( array( 'province_name' => 'bad" autofocus onfocus="alert(1)', 'province_label' => '<script>alert(1)</script>Label', 'default_address' => '"><img src=x onerror=alert(1)>', 'class' => 'custom" onclick="bad' ) );
    $assert( ! str_contains( $escaped, '<script' ) && ! str_contains( $escaped, '<img' ) && ! str_contains( $escaped, ' autofocus ' ) && str_contains( $escaped, 'name="vnap_province"' ), 'Malicious attributes cannot create markup or event handlers' );
    $assert( str_contains( $escaped, 'vnap-custom' ), 'Custom classes receive vnap prefix' );
    $service = Plugin::instance()->address_service(); $provinces = $service->get_provinces(); $p = $provinces[0];
    $detail = $service->get_wards( (int) $p['code'] ); $w = $detail['wards'][0];
    $preselect = AddressComponent::render( array( 'default_province' => $p['code'], 'default_ward' => $w['code'] ) );
    $assert( str_contains( $preselect, 'value="' . $p['code'] . '" selected=' ) && str_contains( $preselect, 'data-vnap-default-ward="' . $w['code'] . '"' ), 'Initial province rendered and ward passed as code only' );
    $assert( ! is_wp_error( $service->validate_selection( (string) $p['code'], (string) $w['code'] ) ), 'Server validator resolves valid parent/ward names by codes' );
    $other = $service->get_wards( (int) $provinces[1]['code'] );
    $assert( is_wp_error( $service->validate_selection( $p['code'], $other['wards'][0]['code'] ) ), 'Server validator rejects cross-province ward' );
    $assert( is_wp_error( $service->validate_selection( 999999, $w['code'] ) ) && is_wp_error( $service->validate_selection( $p['code'], 999999 ) ), 'Unknown province/ward rejected' );
    foreach ( array( -1, 0, array( 1 ), 1.5, '1abc', true, str_repeat( '9', 30 ) ) as $bad ) { $assert( is_wp_error( $service->validate_selection( $bad, $w['code'] ) ), 'Strict selection input rejects ' . gettype( $bad ) ); }
    $filter = static fn( $args ) => array_merge( $args, array( 'province_label' => 'Filtered label' ) );
    add_filter( 'vnap_frontend_component_config', $filter );
    $assert( str_contains( vnap_render_address_fields(), 'Filtered label' ), 'Prefixed PHP helper and configuration filter' );
    remove_filter( 'vnap_frontend_component_config', $filter );
    $assert( $http === 0, 'Rendering and selection validation make zero remote calls' );
    $assert( count( array_keys( wp_scripts()->queue, Assets::HANDLE, true ) ) === 1, 'Multiple instances enqueue once' );
    wp_dequeue_script( Assets::HANDLE ); wp_dequeue_style( Assets::HANDLE );
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php'; set_current_screen( 'dashboard' );
    Assets::enqueue();
    $assert( ! wp_script_is( Assets::HANDLE, 'enqueued' ) && ! wp_style_is( Assets::HANDLE, 'enqueued' ), 'Frontend assets are not enqueued in wp-admin' );
    WP_CLI::success( $count . ' frontend PHP checks passed.' );
} finally { restore_error_handler(); remove_filter( 'pre_http_request', $block, PHP_INT_MAX ); }
