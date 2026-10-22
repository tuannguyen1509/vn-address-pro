<?php
namespace Tunlamweb\VNAddressPro\Migration;
defined( 'ABSPATH' ) || exit;

final class LegacyAddressAnalysis {
    public function __construct( public readonly array $data ) {}
    public function to_array(): array { return $this->data; }
}
