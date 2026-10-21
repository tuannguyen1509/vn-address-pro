<?php
namespace Tunlamweb\VNAddressPro\Database;

defined( 'ABSPATH' ) || exit;

final class SchemaManager {
    public const VERSION = 1;

    public function ensure( bool $force = false ): bool|\WP_Error {
        global $wpdb;
        if ( ! $force && (int) get_option( 'vnap_db_version', 0 ) === self::VERSION ) {
            return true;
        }
        if ( (int) get_option( 'vnap_db_version', 0 ) > self::VERSION ) {
            return new \WP_Error( 'vnap_dataset_unavailable', __( 'The installed address schema is newer than this plugin supports.', 'vn-address-pro' ) );
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $provinces = TableNames::provinces();
        $wards = TableNames::wards();
        $charset = $wpdb->get_charset_collate();
        $old = $wpdb->suppress_errors();
        try {
            dbDelta( "CREATE TABLE $provinces (
                code bigint(20) unsigned NOT NULL,
                name varchar(191) NOT NULL,
                division_type varchar(191) NOT NULL DEFAULT '',
                codename varchar(191) NOT NULL DEFAULT '',
                phone_code int unsigned DEFAULT NULL,
                PRIMARY KEY  (code)
            ) ENGINE=InnoDB $charset;" );
            dbDelta( "CREATE TABLE $wards (
                code bigint(20) unsigned NOT NULL,
                province_code bigint(20) unsigned NOT NULL,
                name varchar(191) NOT NULL,
                division_type varchar(191) NOT NULL DEFAULT '',
                codename varchar(191) NOT NULL DEFAULT '',
                position int unsigned NOT NULL,
                PRIMARY KEY  (code),
                KEY province_position (province_code,position)
            ) ENGINE=InnoDB $charset;" );
            foreach ( array( $provinces, $wards, $wpdb->options ) as $table ) {
                $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
                if ( strtoupper( (string) $engine ) !== 'INNODB' ) {
                    return new \WP_Error( 'vnap_dataset_unavailable', __( 'Address installation requires transactional InnoDB tables, including WordPress options.', 'vn-address-pro' ) );
                }
            }
            // Verify usable columns, not just the existence of a dbDelta result.
            $wpdb->get_results( "SELECT code,name,division_type,codename,phone_code FROM $provinces LIMIT 0" );
            if ( $wpdb->last_error ) {
                return $this->error();
            }
            $wpdb->get_results( "SELECT code,province_code,name,division_type,codename,position FROM $wards LIMIT 0" );
            if ( $wpdb->last_error ) {
                return $this->error();
            }
            update_option( 'vnap_db_version', self::VERSION, false );
            return true;
        } finally {
            $wpdb->suppress_errors( $old );
        }
    }

    private function error(): \WP_Error {
        return new \WP_Error( 'vnap_dataset_unavailable', __( 'The local address schema could not be installed.', 'vn-address-pro' ) );
    }
}
