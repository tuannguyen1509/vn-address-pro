<?php
namespace Tunlamweb\VNAddressPro\Contracts;

defined( 'ABSPATH' ) || exit;

/** Raw arrays or WP_Error; no REST envelope or normalized address records. */
interface AddressProviderInterface {
    public function get_provinces(): array|\WP_Error;

    public function get_province( int $province_code ): array|\WP_Error;

    /** Raw province detail including wards and parent context, sharing detail cache. */
    public function get_wards( int $province_code ): array|\WP_Error;
}
