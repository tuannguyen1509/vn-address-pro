<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\WPForms;
use Tunlamweb\VNAddressPro\Integrations\Forms\Contracts\FormAdapterInterface;
defined( 'ABSPATH' ) || exit;

final class WPFormsIntegration implements FormAdapterInterface {
    public const MIN_VERSION = '2.0.3.1';
    private bool $booted = false;
    public function get_id(): string { return 'wpforms'; }
    public function is_available(): bool {
        return defined( 'WPFORMS_VERSION' ) && version_compare( WPFORMS_VERSION, self::MIN_VERSION, '>=' ) && function_exists( 'wpforms' ) && class_exists( '\WPForms_Field' ) && ! wpforms()->is_pro();
    }
    public function status_details(): array {
        return array( 'version' => defined( 'WPFORMS_VERSION' ) ? WPFORMS_VERSION : '', 'scope' => 'WPForms Lite standard forms; Pro/conditional/repeater not certified' );
    }
    public function boot(): void {
        if ( $this->booted || ! $this->is_available() ) { return; }
        $this->booted = true;
        new AddressField();
    }
}
