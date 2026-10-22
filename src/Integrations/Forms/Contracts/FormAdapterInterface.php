<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\Contracts;
defined( 'ABSPATH' ) || exit;

interface FormAdapterInterface {
    public function get_id(): string;
    public function is_available(): bool;
    public function boot(): void;
}
