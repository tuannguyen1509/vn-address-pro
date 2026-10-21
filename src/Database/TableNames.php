<?php
namespace Tunlamweb\VNAddressPro\Database;

defined( 'ABSPATH' ) || exit;

final class TableNames {
    public static function provinces(): string {
        global $wpdb;
        return $wpdb->prefix . 'vnap_provinces';
    }

    public static function wards(): string {
        global $wpdb;
        return $wpdb->prefix . 'vnap_wards';
    }
}
