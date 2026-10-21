<?php
/** Disposable browser fixture MU plugin; never loaded by production bootstrap. */
defined( 'ABSPATH' ) || exit;
add_action( 'template_redirect', static function () {
    $mode = isset( $_GET['vnap_test_page'] ) && is_string( $_GET['vnap_test_page'] ) ? sanitize_key( $_GET['vnap_test_page'] ) : '';
    if ( ! in_array( $mode, array( 'component','plain' ), true ) ) { return; }
    nocache_headers();
    echo '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VN Address test</title>';
    wp_head(); echo '</head><body><main>';
    if ( $mode === 'component' ) {
        $service = \Tunlamweb\VNAddressPro\Plugin::instance()->address_service();
        $provinces = $service->get_provinces();
        $p = $provinces[0]; $wards = $service->get_wards( $p['code'] ); $ward = $wards['wards'][0];
        echo '<form id="vnap-test-form">';
        echo do_shortcode( '[vn_address_fields required="yes"]' );
        echo do_shortcode( '[vn_address_fields show_address="no" province_name="other[province]" ward_name="other[ward]"]' );
        echo \Tunlamweb\VNAddressPro\Frontend\AddressComponent::render( array( 'default_province' => $p['code'], 'default_ward' => $ward['code'], 'default_address' => 'Example address' ) );
        echo '<button type="reset">Reset</button></form>';
    }
    echo '</main>'; wp_footer(); echo '</body></html>'; exit;
} );
