<?php
namespace Tunlamweb\VNAddressPro\Integrations\WooCommerce;
defined( 'ABSPATH' ) || exit;

final class WooCommerceIntegration {
    private static bool $loaded = false;
    public static function load(): void {
        if ( self::$loaded || ! \Tunlamweb\VNAddressPro\Support\IntegrationSettings::enabled( 'woocommerce' ) || ! Compatibility::supported() ) { return; }
        self::$loaded = true;
        ( new States() )->register();
        ( new AddressFields() )->register();
        ( new ClassicCheckout() )->register();
        ( new CustomerAddress() )->register();
        ( new OrderAddress() )->register();
        ( new Assets() )->register();
        ( new CompactCheckout() )->register();
        ( new VatFields() )->register();
        ( new MyAccount\MyAccountIntegration() )->register();
        ( new Admin\OrderAddress() )->register();
        if ( Compatibility::supports_blocks() ) { Blocks\BlocksIntegration::register(); }
        add_action( 'before_woocommerce_init', array( Compatibility::class, 'declare' ) );
    }
}
