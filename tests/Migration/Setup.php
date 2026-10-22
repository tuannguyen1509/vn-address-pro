<?php
/** Disposable browser fixtures only; never run against merchant data. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$ward = ( new Tunlamweb\VNAddressPro\Repository\LocalAddressRepository() )->get_wards( 79 )['wards'][0];
$ids = array();
foreach ( array( 'customer','order' ) as $type ) {
    if ( $type === 'customer' ) { $id = wc_create_new_customer( 'vnap-migration-browser-' . wp_generate_uuid4() . '@example.invalid' ); $entity = new WC_Customer( $id ); }
    else { $entity = wc_create_order(); }
    $entity->set_billing_country( 'VN' ); $entity->set_billing_state( 'VN-79' ); $entity->set_billing_city( $ward['name'] );
    $entity->set_billing_first_name( 'Do not expose synthetic name' ); $entity->set_billing_address_1( 'Do not expose synthetic street' ); $entity->save();
    $ids[$type] = $entity->get_id();
}
update_option( 'show_avatars', 0 ); WP_CLI::line( wp_json_encode( $ids ) );
