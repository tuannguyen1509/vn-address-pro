<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
use Tunlamweb\VNAddressPro\Integrations\Forms\FormIntegrationRegistry;
use Tunlamweb\VNAddressPro\Integrations\Forms\Contracts\FormAdapterInterface;
use Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7\ContactForm7Integration;
$registry = new FormIntegrationRegistry();
$adapter = new class implements FormAdapterInterface {
    public int $boots = 0;
    public function get_id(): string { return 'synthetic'; }
    public function is_available(): bool { return true; }
    public function boot(): void { ++$this->boots; }
};
if ( ! $registry->register( $adapter ) || $registry->register( $adapter ) ) { throw new RuntimeException( 'Registry duplicate protection' ); }
$registry->boot_available(); $registry->boot_available(); if ( $adapter->boots !== 1 ) { throw new RuntimeException( 'Single boot' ); }
if ( ( new ContactForm7Integration() )->is_available() ) { throw new RuntimeException( 'Run with CF7 inactive' ); }
if ( is_wp_error( Tunlamweb\VNAddressPro\Plugin::instance()->address_service()->get_provinces() ) || ! str_contains( do_shortcode( '[vn_address_fields]' ), 'data-vnap-address' ) ) { throw new RuntimeException( 'Absent CF7 breaks independent core' ); }
WP_CLI::success( 'Forms absent: registry duplicate/single boot, conditional adapter, core and shortcode passed.' );
