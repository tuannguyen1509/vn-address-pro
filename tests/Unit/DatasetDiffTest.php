<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
use Tunlamweb\VNAddressPro\Data\DatasetDiff;
use Tunlamweb\VNAddressPro\Sync\DatasetSyncResult;
$before = array( 'provinces' => array( array( 'code' => 1, 'name' => 'Province A', 'wards' => array( array( 'code' => 1, 'name' => 'Ward A' ), array( 'code' => 2, 'name' => 'Ward B' ) ) ), array( 'code' => 2, 'name' => 'Province B', 'wards' => array() ) ) );
$after = array( 'provinces' => array( array( 'code' => 1, 'name' => 'Province Renamed', 'wards' => array( array( 'code' => 3, 'name' => 'Ward New' ) ) ), array( 'code' => 3, 'name' => 'Province New', 'wards' => array( array( 'code' => 1, 'name' => 'Ward Renamed' ) ) ) ) );
$diff = ( new DatasetDiff() )->compare( $before, $after );
foreach ( array( 'provinces_added','provinces_removed','provinces_renamed','wards_added','wards_removed','wards_renamed','wards_reparented' ) as $key ) { if ( $diff[$key] !== 1 ) { throw new RuntimeException( $key ); } }
$same = ( new DatasetDiff() )->compare( $before, $before );
foreach ( array( 'provinces_added','provinces_removed','provinces_renamed','wards_added','wards_removed','wards_renamed','wards_reparented' ) as $key ) { if ( $same[$key] !== 0 ) { throw new RuntimeException( $key ); } }
$encoded = ( new DatasetSyncResult( DatasetSyncResult::UPDATE_AVAILABLE, 'source', 'old', 'new', $diff ) )->to_array();
if ( $encoded['changes'] !== $diff || isset( ( new DatasetSyncResult( DatasetSyncResult::DOWNLOAD_ERROR ) )->to_array()['changes'] ) ) { throw new RuntimeException( 'Result compatibility' ); }
WP_CLI::success( '15 dataset change-summary checks passed.' );
