<?php
namespace Tunlamweb\VNAddressPro;

use Tunlamweb\VNAddressPro\Compatibility\Legacy;
use Tunlamweb\VNAddressPro\Support\Requirements;

defined( 'ABSPATH' ) || exit;

/** Bootstrap and lifecycle only. Address/REST logic remains in the legacy API. */
final class Plugin {
    private static ?self $instance = null;
    private bool $booted = false;

    private function __construct() {}
    private function __clone() {}

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        Legacy::load();
        $this->booted = true;
        add_action( 'init', array( $this, 'load_textdomain' ), 0 );
        add_action( 'init', array( $this, 'maybe_upgrade' ), 1 );
        /** Core is ready and REST registration is scheduled; no arguments. */
        do_action( 'vnap_loaded' );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'vn-address-pro', false, dirname( VNAP_BASENAME ) . '/languages' );
    }

    public function maybe_upgrade(): void {
        $installed = get_option( 'vnap_version', '' );
        if ( $installed === VNAP_VERSION ) {
            return;
        }
        // Never downgrade a version marker written by a newer release.
        if ( $installed !== '' && version_compare( $installed, VNAP_VERSION, '>' ) ) {
            return;
        }
        // Future ordered migrations belong here, before advancing this marker.
        update_option( 'vnap_version', VNAP_VERSION, false );
    }

    public static function activate( bool $network_wide = false ): void {
        if ( ! Requirements::met() ) {
            Requirements::reject_activation();
        }
        // On multisite each site's marker is initialized lazily on its own init.
        self::instance()->maybe_upgrade();
    }

    public static function deactivate( bool $network_wide = false ): void {
        // No scheduled jobs or temporary resources in Phase 1. Preserve options/cache.
    }
}
