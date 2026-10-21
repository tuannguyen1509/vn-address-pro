<?php
namespace Tunlamweb\VNAddressPro\Data;

defined( 'ABSPATH' ) || exit;

final class DatasetLoader {
    private const MAX_BYTES = 16 * 1024 * 1024;
    public function __construct( private ?string $directory = null ) {}

    public function load(): array|\WP_Error {
        $directory = $this->directory ?? VNAP_PATH . 'data';
        $bytes = is_readable( $directory . '/vn-address.json' ) ? @file_get_contents( $directory . '/vn-address.json', false, null, 0, self::MAX_BYTES + 1 ) : false;
        $manifest_bytes = is_readable( $directory . '/manifest.json' ) ? @file_get_contents( $directory . '/manifest.json', false, null, 0, 65537 ) : false;
        if ( $bytes === false || $manifest_bytes === false || strlen( $bytes ) > self::MAX_BYTES || strlen( $manifest_bytes ) > 65536 ) {
            return $this->error();
        }
        $data = json_decode( $bytes, true );
        $manifest = json_decode( $manifest_bytes, true );
        if ( ! is_array( $manifest ) || ! ( new DatasetValidator() )->validate( $data )
            || ( $manifest['schema_version'] ?? null ) !== $data['schema_version']
            || ( $manifest['dataset_version'] ?? null ) !== $data['dataset_version']
            || ! is_string( $manifest['source'] ?? null ) || trim( $manifest['source'] ) === ''
            || ! is_string( $manifest['generated_at'] ?? null )
            || $manifest['generated_at'] !== \DateTimeImmutable::createFromFormat( '!YmdHis', $data['dataset_version'], new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' )
            || ! is_string( $manifest['checksum'] ?? null ) || ! hash_equals( hash( 'sha256', $bytes ), $manifest['checksum'] ) ) {
            return $this->error();
        }
        $count = 0;
        foreach ( $data['provinces'] as $province ) {
            $count += count( $province['wards'] );
        }
        if ( ( $manifest['province_count'] ?? null ) !== count( $data['provinces'] ) || ( $manifest['ward_count'] ?? null ) !== $count ) {
            return $this->error();
        }
        return array( 'dataset' => $data, 'manifest' => $manifest );
    }

    private function error(): \WP_Error {
        return new \WP_Error( 'vnap_invalid_payload', __( 'The bundled address dataset or manifest failed integrity validation.', 'vn-address-pro' ) );
    }
}
