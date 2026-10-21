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
use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;
use Tunlamweb\VNAddressPro\Repository\LocalAddressRepository;
use Tunlamweb\VNAddressPro\Repository\RemoteAddressRepository;
use Tunlamweb\VNAddressPro\Repository\FallbackAddressRepository;
use Tunlamweb\VNAddressPro\Data\DatasetInstaller;

defined( 'ABSPATH' ) || exit;

/** Composition root and lifecycle only. No address or REST business logic. */
final class Plugin {
    private static ?self $instance = null;
    private bool $booted = false;
    private ?AddressService $address_service = null;
    private ?AddressController $rest_controller = null;
    private ?AddressRepositoryInterface $repository = null;

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
        ( new \Tunlamweb\VNAddressPro\Sync\DatasetScheduler() )->register();
        ( new \Tunlamweb\VNAddressPro\Admin\SettingsPage() )->register();
        $this->booted = true;
        add_action( 'init', array( $this, 'load_textdomain' ), 0 );
        add_action( 'init', array( $this, 'maybe_upgrade' ), 1 );
        add_action( 'admin_notices', array( $this, 'dataset_notice' ) );
        add_action( 'network_admin_notices', array( $this, 'dataset_notice' ) );
        /** Core is ready and REST registration is scheduled; no arguments. */
        do_action( 'vnap_loaded' );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'vn-address-pro', false, dirname( VNAP_BASENAME ) . '/languages' );
    }

    /** Internal, provisional API; the stable developer facade is a later phase. */
    public function address_service(): AddressService {
        if ( $this->address_service === null ) {
            $this->address_service = new AddressService( $this->repository(), new Normalizer(), new Validator(), new Search() );
        }
        return $this->address_service;
    }

    /** Internal composition access; local is always preferred when usable. */
    public function repository(): AddressRepositoryInterface {
        if ( $this->repository === null ) {
            $remote = new RemoteAddressRepository( new ProvinceOpenApiProvider( new HttpClient(), new AddressCache() ) );
            $this->repository = new FallbackAddressRepository( new LocalAddressRepository(), $remote );
        }
        return $this->repository;
    }

    public function dataset_notice(): void {
        if ( current_user_can( 'activate_plugins' ) && get_transient( 'vnap_dataset_install_failure' ) !== false ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'VN Address Pro could not install its bundled local dataset. Existing local data is preserved; remote fallback remains available. Check the plugin package and database permissions.', 'vn-address-pro' ) . '</p></div>';
        }
    }

    /** Internal compatibility access, not a stable developer API. */
    public function rest_controller(): AddressController {
        if ( $this->rest_controller === null ) {
            $this->rest_controller = new AddressController( $this->address_service() );
        }
        return $this->rest_controller;
    }

    public function maybe_upgrade(): void {
        // Schema/dataset versions are independent of the unchanged plugin release version.
        ( new DatasetInstaller() )->ensure();
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
        $result = ( new DatasetInstaller() )->ensure( true );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Address dataset installation failed', 'vn-address-pro' ), array( 'back_link' => true ) );
        }
        // On multisite each site's marker is initialized lazily on its own init.
        self::instance()->maybe_upgrade();
        ( new \Tunlamweb\VNAddressPro\Sync\DatasetScheduler() )->reconcile();
    }

    public static function deactivate( bool $network_wide = false ): void {
        \Tunlamweb\VNAddressPro\Sync\DatasetScheduler::clear();
    }
}
