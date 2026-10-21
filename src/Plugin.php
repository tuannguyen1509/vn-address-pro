<?php
namespace Tunlamweb\VNAddressPro;

use Tunlamweb\VNAddressPro\Compatibility\Legacy;
use Tunlamweb\VNAddressPro\Support\Requirements;
use Tunlamweb\VNAddressPro\Address\AddressService;
use Tunlamweb\VNAddressPro\Address\Normalizer;
use Tunlamweb\VNAddressPro\Address\Validator;
use Tunlamweb\VNAddressPro\Cache\AddressCache;
use Tunlamweb\VNAddressPro\Http\HttpClient;
use Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider;
use Tunlamweb\VNAddressPro\Rest\V1\AddressController;
use Tunlamweb\VNAddressPro\Support\Search;

defined( 'ABSPATH' ) || exit;

/** Composition root and lifecycle only. No address or REST business logic. */
final class Plugin {
    private static ?self $instance = null;
    private bool $booted = false;
    private ?AddressService $address_service = null;
    private ?AddressController $rest_controller = null;

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
        $this->rest_controller();
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

    /** Internal, provisional API; the stable developer facade is a later phase. */
    public function address_service(): AddressService {
        if ( $this->address_service === null ) {
            $provider = new ProvinceOpenApiProvider( new HttpClient(), new AddressCache() );
            $this->address_service = new AddressService( $provider, new Normalizer(), new Validator(), new Search() );
        }
        return $this->address_service;
    }

    /** Internal compatibility access, not a stable developer API. */
    public function rest_controller(): AddressController {
        if ( $this->rest_controller === null ) {
            $this->rest_controller = new AddressController( $this->address_service() );
        }
        return $this->rest_controller;
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
        // No scheduled jobs or temporary resources. Preserve options/cache.
    }
}
