<?php
namespace Tunlamweb\VNAddressPro\Sync;
use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider;
use Tunlamweb\VNAddressPro\Data\DatasetIntegrityAuditor;
defined( 'ABSPATH' ) || exit;

final class RemoteCacheCleaner {
    public function clear( array $extra_codes = array() ): void {
        $snapshot = ( new DatasetIntegrityAuditor() )->snapshot( '20000101000000' );
        $codes = $extra_codes;
        if ( ! is_wp_error( $snapshot ) ) { $codes = array_merge( $codes, array_column( $snapshot['provinces'], 'code' ) ); }
        foreach ( array_merge( array( null ), array_unique( $codes ) ) as $code ) {
            $keys = ProvinceOpenApiProvider::runtime_keys( $code === null ? null : (int) $code );
            foreach ( array( 'active','negative','failure' ) as $kind ) { delete_transient( $keys[ $kind ] ); }
        }
        // Never remove LKG or in-flight refresh leases. No wildcard transient deletion.
    }
}
