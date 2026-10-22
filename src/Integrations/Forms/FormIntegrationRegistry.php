<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms;
use Tunlamweb\VNAddressPro\Integrations\Forms\Contracts\FormAdapterInterface;
defined( 'ABSPATH' ) || exit;

/** No plugin-directory discovery, framework or form-builder dependency. */
final class FormIntegrationRegistry {
    private array $adapters = array();
    private array $booted = array();
    public function register( FormAdapterInterface $adapter ): bool {
        $id = $adapter->get_id();
        if ( ! preg_match( '/\A[a-z][a-z0-9-]*\z/', $id ) || isset( $this->adapters[$id] ) ) { return false; }
        $this->adapters[$id] = $adapter; return true;
    }
    public function boot_available(): void {
        foreach ( $this->adapters as $id => $adapter ) {
            if ( ! isset( $this->booted[$id] ) && $adapter->is_available() ) { $adapter->boot(); $this->booted[$id] = true; }
        }
    }
    public function status(): array {
        $status = array();
        foreach ( $this->adapters as $id => $adapter ) {
            $status[$id] = array( 'available' => $adapter->is_available(), 'booted' => isset( $this->booted[$id] ) );
            if ( is_callable( array( $adapter, 'status_details' ) ) ) { $status[$id] += $adapter->status_details(); }
        }
        return $status;
    }
}
