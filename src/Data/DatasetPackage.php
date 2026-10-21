<?php
namespace Tunlamweb\VNAddressPro\Data;
defined( 'ABSPATH' ) || exit;

/** Canonical byte integrity and version-independent database-content identity. */
final class DatasetPackage {
    public static function bytes( array $data ): string {
        return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
    }

    public static function content_checksum( array $data ): string {
        $records = array();
        foreach ( $data['provinces'] as $p ) {
            $row = array( 'code' => $p['code'], 'name' => $p['name'], 'division_type' => $p['division_type'] ?? '', 'codename' => $p['codename'] ?? '', 'phone_code' => $p['phone_code'] ?? null, 'wards' => array() );
            foreach ( $p['wards'] as $w ) {
                $row['wards'][] = array( 'code' => $w['code'], 'province_code' => $w['province_code'], 'name' => $w['name'], 'division_type' => $w['division_type'] ?? '', 'codename' => $w['codename'] ?? '' );
            }
            $records[] = $row;
        }
        usort( $records, static fn( $a, $b ) => $a['code'] <=> $b['code'] );
        return hash( 'sha256', self::bytes( $records ) );
    }

    public static function build( array $data, string $source ): array {
        $count = 0;
        foreach ( $data['provinces'] as $p ) { $count += count( $p['wards'] ); }
        return array( 'dataset' => $data, 'manifest' => array(
            'schema_version' => $data['schema_version'], 'dataset_version' => $data['dataset_version'], 'source' => $source,
            'generated_at' => \DateTimeImmutable::createFromFormat( '!YmdHis', $data['dataset_version'], new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ),
            'province_count' => count( $data['provinces'] ), 'ward_count' => $count,
            'checksum' => hash( 'sha256', self::bytes( $data ) ), 'content_checksum' => self::content_checksum( $data ),
        ) );
    }

    public static function valid( array $package ): bool {
        $d = $package['dataset'] ?? null; $m = $package['manifest'] ?? null;
        if ( ! ( new DatasetValidator() )->validate( $d ) || ! is_array( $m ) ) { return false; }
        $expected = self::build( $d, 'validation' )['manifest'];
        foreach ( array( 'schema_version', 'dataset_version', 'generated_at', 'province_count', 'ward_count', 'checksum' ) as $key ) {
            if ( ( $m[ $key ] ?? null ) !== $expected[ $key ] ) { return false; }
        }
        return is_string( $m['source'] ?? null ) && trim( $m['source'] ) !== '' && strlen( $m['source'] ) <= 191
            && ( ! isset( $m['content_checksum'] ) || $m['content_checksum'] === $expected['content_checksum'] );
    }
}
