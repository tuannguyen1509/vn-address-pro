<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms;
use Tunlamweb\VNAddressPro\Address\AddressService;
defined( 'ABSPATH' ) || exit;

/** Codes are untrusted until core validation succeeds; no browser-name authority. */
final class AddressSubmission {
    public function __construct( public readonly mixed $province_code, public readonly mixed $ward_code ) {}
    public function resolve( AddressService $service ): array|\WP_Error { return $service->validate_selection( $this->province_code, $this->ward_code ); }
}
