<?php
/** Optional live-provider read-only check; excludes upstream availability from deterministic suites. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$vnap_package = ( new \Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiDatasetProvider() )->fetch_dataset();
if ( is_wp_error( $vnap_package ) || ! \Tunlamweb\VNAddressPro\Data\DatasetPackage::valid( $vnap_package ) ) { WP_CLI::error( 'Live remote dataset download/validation failed.' ); }
WP_CLI::success( 'Live full dataset validated without publication: ' . $vnap_package['manifest']['province_count'] . ' provinces, ' . $vnap_package['manifest']['ward_count'] . ' wards.' );
