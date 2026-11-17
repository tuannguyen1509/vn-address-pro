<?php
/** Read-only checks in a WordPress CLI process; no customer or order writes. */
defined( 'ABSPATH' ) || exit;

use Tunlamweb\VNAddressPro\Admin\{SettingsPage,Presentation};

$check = static function ( bool $ok, string $message ): void {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
    echo 'PASS ' . $message . PHP_EOL;
};
$queue = wp_styles()->queue;
$before = get_option( 'vnap_dataset_metadata' );
try {
    wp_dequeue_style( 'vnap-admin' );
    $page = new SettingsPage();
    foreach ( array( 'index.php', 'plugins.php', 'woocommerce_page_wc-settings' ) as $hook ) {
        $page->assets( $hook );
        $check( ! wp_style_is( 'vnap-admin', 'enqueued' ), 'Admin CSS isolated from ' . $hook );
    }
    $page->assets( 'settings_page_vn-address-pro' );
    $check( wp_style_is( 'vnap-admin', 'enqueued' ), 'Admin CSS loaded on plugin settings' );
    $check( wp_styles()->registered['vnap-admin']->ver === VNAP_VERSION, 'Admin CSS uses release version' );
    load_textdomain( 'vn-address-pro', VNAP_PATH . 'languages/vn-address-pro-vi.mo', 'vi' );
    $check( __( 'General', 'vn-address-pro' ) === 'Tổng quan', 'Vietnamese catalog loads' );
    $check( Presentation::value( 'disabled' ) === 'Đã tắt', 'Stored schedule has Vietnamese display label' );
    $check( Presentation::value( 'UNRECOGNIZED_CODE' ) === 'UNRECOGNIZED_CODE', 'Unknown diagnostic codes preserved' );
    $check( get_option( 'vnap_dataset_metadata' ) === $before, 'Presentation does not modify dataset metadata' );
    switch_to_locale( 'en_US' );
    $check( __( 'General', 'vn-address-pro' ) === 'General', 'English fallback retained' );
} finally {
    restore_current_locale();
    wp_styles()->queue = $queue;
}
