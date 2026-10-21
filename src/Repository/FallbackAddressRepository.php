<?php
namespace Tunlamweb\VNAddressPro\Repository;

use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class FallbackAddressRepository implements AddressRepositoryInterface {
    public function __construct( private AddressRepositoryInterface $local, private AddressRepositoryInterface $remote ) {}

    public function has_usable_dataset(): bool { return $this->local->has_usable_dataset(); }
    public function get_provinces(): array|\WP_Error { return $this->lookup( 'get_provinces' ); }
    public function get_province( int $province_code ): array|\WP_Error { return $this->lookup( 'get_province', $province_code ); }
    public function get_wards( int $province_code ): array|\WP_Error { return $this->lookup( 'get_wards', $province_code ); }
    public function get_ward( int $ward_code ): array|\WP_Error { return $this->lookup( 'get_ward', $ward_code ); }

    private function lookup( string $method, ?int $code = null ): array|\WP_Error {
        $args = $code === null ? array() : array( $code );
        if ( $this->local->has_usable_dataset() ) {
            $result = $this->local->$method( ...$args );
            if ( ! is_wp_error( $result ) || $result->get_error_code() !== 'vnap_dataset_unavailable' ) {
                return $result; // Not found is an authoritative local result, never an HTTP trigger.
            }
        }
        return $this->remote->$method( ...$args );
    }
}
