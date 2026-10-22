<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms;
defined( 'ABSPATH' ) || exit;

/** Adapter-neutral field description; group is an explicit province field name. */
final class AddressFieldConfig {
    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly bool $required = false,
        public readonly string $group = '',
        public readonly string $default = '',
        public readonly string $label = '',
        public readonly string $placeholder = ''
    ) {}
}
