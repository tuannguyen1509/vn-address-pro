<?php
/** Run in a disposable WordPress database; restores owned integration preferences. */
defined( 'ABSPATH' ) || exit;
use Tunlamweb\VNAddressPro\Admin\SettingsPage;
use Tunlamweb\VNAddressPro\Support\IntegrationSettings;

$original = get_option( IntegrationSettings::OPTION, null );
$actor = get_current_user_id(); $get = $_GET; $count = 0;
$check = static function ( $ok, $message ) use ( &$count ) {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
    ++$count; echo 'PASS ' . $message . PHP_EOL;
};
try {
    delete_option( IntegrationSettings::OPTION );
    foreach ( IntegrationSettings::IDS as $id ) { $check( IntegrationSettings::enabled( $id ), 'Default integration stays enabled: ' . $id ); }
    IntegrationSettings::save( array( 'wpforms', 'unregistered', array( 'woocommerce' ) ) );
    $check( ! IntegrationSettings::enabled( 'woocommerce' ) && IntegrationSettings::enabled( 'wpforms' ), 'Only exact selected integrations enabled' );
    $check( array_keys( get_option( IntegrationSettings::OPTION ) ) === IntegrationSettings::IDS, 'Unregistered input never becomes a stored preference' );
    $check( IntegrationSettings::enabled( 'third-party-adapter' ), 'External adapters retain their defaults' );
    $adapter = new class implements \Tunlamweb\VNAddressPro\Integrations\Forms\Contracts\FormAdapterInterface {
        public int $calls = 0;
        public function get_id(): string { return 'wpforms'; }
        public function is_available(): bool { return true; }
        public function boot(): void { ++$this->calls; }
    };
    $registry = new \Tunlamweb\VNAddressPro\Integrations\Forms\FormIntegrationRegistry(); $registry->register( $adapter );
    IntegrationSettings::save( array() ); $registry->boot_available();
    $check( $adapter->calls === 0, 'Available form adapter does not boot when disabled' );
    IntegrationSettings::save( array( 'wpforms' ) ); $registry->boot_available(); $registry->boot_available();
    $check( $adapter->calls === 1, 'Reenabled form adapter boots exactly once' );
    wp_set_current_user( 1 );
    $page = new SettingsPage();
    foreach ( array( 'general','settings','advanced','data','tools','status','migration','shipping' ) as $tab ) {
        $_GET = array( 'tab' => $tab ); ob_start(); $page->render(); $html = ob_get_clean();
        preg_match( '/<nav class="nav-tab-wrapper".*?<\/nav>/s', $html, $nav );
        $check( substr_count( $nav[0] ?? '', '<a ' ) === 3, 'Exactly three main tabs on ' . $tab );
        if ( $tab === 'general' ) { $check( ! str_contains( $html, 'vnap_command" value="schedule' ) && ! str_contains( $html, 'vnap_command" value="repair' ), 'Overview excludes configuration and repair actions' ); }
        if ( $tab === 'settings' ) { $check( str_contains( $html, 'name="vnap_enabled[]"' ) && str_contains( $html, 'value="integrations"' ) && str_contains( $html, 'name="_wpnonce"' ), 'Setup has protected integration controls' ); }
        if ( in_array( $tab, array( 'advanced','data','tools','status' ), true ) ) { $check( str_contains( $html, '<details class="vnap-disclosure">' ), 'Advanced technical controls start collapsed: ' . $tab ); }
    }
    wp_set_current_user( 0 );
    $check( ! $page->authorize( 'integrations', wp_create_nonce( 'vnap_integrations' ) ), 'Anonymous cannot save integration preferences' );
    wp_set_current_user( 1 );
    $check( ! $page->authorize( 'integrations', 'invalid' ), 'Integration save rejects invalid nonce' );
    $check( $page->authorize( 'integrations', wp_create_nonce( 'vnap_integrations' ) ), 'Administrator with valid nonce can save' );
    echo 'SUCCESS ' . $count . ' simplified settings checks' . PHP_EOL;
} finally {
    if ( $original === null ) { delete_option( IntegrationSettings::OPTION ); } else { update_option( IntegrationSettings::OPTION, $original, false ); }
    wp_set_current_user( $actor ); $_GET = $get;
}
