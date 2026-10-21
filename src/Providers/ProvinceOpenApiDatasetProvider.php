<?php
namespace Tunlamweb\VNAddressPro\Providers;
use Tunlamweb\VNAddressPro\Contracts\DatasetProviderInterface;
use Tunlamweb\VNAddressPro\Data\{DatasetValidator,DatasetPackage,DatasetMetadata,DatasetLoader,BundledDataset};
use Tunlamweb\VNAddressPro\Providers\Validation\ProvinceOpenApiValidator;
defined( 'ABSPATH' ) || exit;

final class ProvinceOpenApiDatasetProvider implements DatasetProviderInterface {
    public function get_remote_version(): array|\WP_Error {
        $package = $this->fetch_dataset();
        return is_wp_error( $package ) ? $package : $package['manifest'];
    }
    public function fetch_dataset(): array|\WP_Error {
        // Single complete root request: no province-by-province partial merge.
        $response = wp_safe_remote_get( ProvinceOpenApiProvider::UPSTREAM . '/?depth=2', array( 'timeout' => 30, 'redirection' => 0, 'limit_response_size' => 16 * 1024 * 1024 + 1 ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) { return new \WP_Error( 'vnap_dataset_download' ); }
        $bytes = wp_remote_retrieve_body( $response );
        if ( strlen( $bytes ) > 16 * 1024 * 1024 ) { return new \WP_Error( 'vnap_invalid_payload' ); }
        $raw = json_decode( $bytes, true );
        if ( ! ( new ProvinceOpenApiValidator() )->validate( $raw ) ) { return new \WP_Error( 'vnap_invalid_payload' ); }
        $data = array( 'schema_version' => DatasetValidator::SCHEMA_VERSION, 'dataset_version' => gmdate( 'YmdHis' ), 'provinces' => array() );
        foreach ( $raw as $p ) {
            if ( ! isset( $p['wards'] ) || ! is_array( $p['wards'] ) || ! array_is_list( $p['wards'] ) ) { return new \WP_Error( 'vnap_invalid_payload' ); }
            $row = array_intersect_key( $p, array_flip( array( 'code','name','division_type','codename','phone_code','wards' ) ) );
            $row['wards'] = array();
            foreach ( $p['wards'] as $w ) {
                if ( ! is_array( $w ) ) { return new \WP_Error( 'vnap_invalid_payload' ); }
                $ward = array_intersect_key( $w, array_flip( array( 'code','name','division_type','codename','province_code' ) ) );
                $ward['province_code'] = $ward['province_code'] ?? $p['code']; $row['wards'][] = $ward;
            }
            $data['provinces'][] = $row;
        }
        if ( ! ( new DatasetValidator() )->validate( $data ) ) { return new \WP_Error( 'vnap_invalid_payload' ); }
        $m = ( new DatasetMetadata() )->read(); $installed_hash = $m['content_checksum'] ?? null;
        if ( $installed_hash === null && ( $m['dataset_version'] ?? '' ) === BundledDataset::VERSION && ( $m['checksum'] ?? '' ) === BundledDataset::CHECKSUM ) {
            $bundle = ( new DatasetLoader() )->load();
            if ( ! is_wp_error( $bundle ) ) { $installed_hash = DatasetPackage::content_checksum( $bundle['dataset'] ); }
        }
        // Upstream has no snapshot version: unchanged content retains the installed observation version.
        if ( DatasetValidator::valid_version( $m['dataset_version'] ?? null ) && $installed_hash === DatasetPackage::content_checksum( $data ) ) { $data['dataset_version'] = $m['dataset_version']; }
        return DatasetPackage::build( $data, 'Province Open API v2' );
    }
}
