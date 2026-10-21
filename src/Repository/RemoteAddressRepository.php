<?php
namespace Tunlamweb\VNAddressPro\Repository;

use Tunlamweb\VNAddressPro\Contracts\AddressProviderInterface;
use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class RemoteAddressRepository implements AddressRepositoryInterface {
    public function __construct( private AddressProviderInterface $provider ) {}

    public function get_provinces(): array|\WP_Error { return $this->provider->get_provinces(); }
    public function get_province( int $province_code ): array|\WP_Error { return $this->provider->get_province( $province_code ); }
    public function get_wards( int $province_code ): array|\WP_Error { return $this->provider->get_wards( $province_code ); }

    public function get_ward( int $ward_code ): array|\WP_Error {
        // The current remote provider contract has no single-ward lookup; do not scan every province.
        return new \WP_Error( 'vnap_dataset_unavailable', __( 'Single-ward lookup requires the local repository.', 'vn-address-pro' ) );
    }

    public function has_usable_dataset(): bool {
        return false; // No installed persistent snapshot; this does not probe the network.
    }
}
