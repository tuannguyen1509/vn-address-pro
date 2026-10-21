<?php
namespace Tunlamweb\VNAddressPro\Contracts;

defined( 'ABSPATH' ) || exit;

/** Canonical records, not REST responses. Ward collections include their parent context. */
interface AddressRepositoryInterface extends AddressProviderInterface {
    public function get_ward( int $ward_code ): array|\WP_Error;
    public function has_usable_dataset(): bool;
}
