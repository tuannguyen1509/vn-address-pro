<?php
namespace Tunlamweb\VNAddressPro\Address;

use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;
use Tunlamweb\VNAddressPro\Support\Search;

defined( 'ABSPATH' ) || exit;

/** Application results only: no REST envelopes, HTTP URLs or cache ownership. */
final class AddressService {
    public function __construct(
        private AddressRepositoryInterface $repository,
        private Normalizer $normalizer,
        private Validator $validator,
        private Search $search
    ) {}

    public function get_provinces( string $search = '' ): array|\WP_Error {
        $data = $this->repository->get_provinces();
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( ! $this->validator->provinces( $data ) ) {
            return $this->invalid_payload();
        }
        $items = array();
        foreach ( $data as $province ) {
            $item = $this->normalizer->normalize_province( $province );
            if ( $search !== '' && ! $this->search->contains_ci( $item['name'] . ' ' . $item['short_name'], $search ) ) {
                continue;
            }
            $items[] = $item;
        }
        usort( $items, static fn( $a, $b ) => $a['code'] <=> $b['code'] );
        return $items;
    }

    public function get_wards( int $province_code, string $search = '' ): array|\WP_Error {
        $data = $this->repository->get_wards( $province_code );
        $error = $this->validate_detail( $data );
        if ( is_wp_error( $error ) ) {
            return $error;
        }
        $wards = array();
        foreach ( ( $data['wards'] ?? array() ) as $ward ) {
            $item = $this->normalizer->normalize_ward( $ward );
            if ( $search !== '' && ! $this->search->contains_ci( $item['name'], $search ) ) {
                continue;
            }
            $wards[] = $item;
        }
        usort( $wards, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
        return array( 'province' => $this->normalizer->normalize_province( $data ), 'wards' => $wards );
    }

    public function get_province( int $province_code ): array|\WP_Error {
        $data = $this->repository->get_province( $province_code );
        $error = $this->validate_detail( $data );
        if ( is_wp_error( $error ) ) {
            return $error;
        }
        $province = $this->normalizer->normalize_province( $data );
        $province['wards'] = array_map( array( $this->normalizer, 'normalize_ward' ), $data['wards'] ?? array() );
        $province['ward_count'] = count( $province['wards'] );
        return $province;
    }

    /** Strict code/relationship validation for consumers; never trust submitted display names. */
    public function validate_selection( $province_code, $ward_code ): array|\WP_Error {
        $valid_code = static fn( $code ) => ( is_int( $code ) && $code > 0 ) || ( is_string( $code ) && preg_match( '/\A[0-9]+\z/', $code ) && (int) $code > 0 && ltrim( $code, '0' ) === (string) (int) $code );
        if ( ! $valid_code( $province_code ) || ! $valid_code( $ward_code ) ) {
            return new \WP_Error( 'vnap_invalid_selection', __( 'Mã Tỉnh / Thành phố hoặc Phường / Xã không hợp lệ.', 'vn-address-pro' ) );
        }
        $result = $this->get_wards( (int) $province_code );
        if ( is_wp_error( $result ) ) { return $result; }
        if ( (int) $result['province']['code'] === (int) $province_code ) {
            foreach ( $result['wards'] as $ward ) {
                if ( (int) $ward['code'] === (int) $ward_code && (int) $ward['province_code'] === (int) $province_code ) {
                    return array( 'province' => $result['province'], 'ward' => $ward );
                }
            }
        }
        return new \WP_Error( 'vnap_invalid_selection', __( 'Phường / Xã không thuộc Tỉnh / Thành phố đã chọn.', 'vn-address-pro' ) );
    }

    private function validate_detail( array|\WP_Error $data ): bool|\WP_Error {
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        // Preserve the existing not-found branch before inspecting optional ward data.
        if ( empty( $data['code'] ) ) {
            return new \WP_Error( 'vnap_not_found', __( 'The requested province was not found.', 'vn-address-pro' ) );
        }
        return $this->validator->province( $data ) ? true : $this->invalid_payload();
    }

    private function invalid_payload(): \WP_Error {
        return new \WP_Error( 'vnap_invalid_payload', __( 'The provider address structure is invalid.', 'vn-address-pro' ) );
    }
}
