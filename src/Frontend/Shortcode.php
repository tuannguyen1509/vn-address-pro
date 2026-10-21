<?php
namespace Tunlamweb\VNAddressPro\Frontend;
defined( 'ABSPATH' ) || exit;

final class Shortcode {
    public function register(): void { add_shortcode( 'vn_address_fields', array( $this, 'render' ) ); }
    public function render( $attributes = array() ): string { return AddressComponent::render( is_array( $attributes ) ? $attributes : array() ); }
}
