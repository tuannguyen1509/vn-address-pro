<?php
/** Additional disposable MU fixture, installed alongside WooCommerce/OfflineFixture.php. */
defined( 'ABSPATH' ) || exit;
add_action( 'woocommerce_rest_checkout_process_payment_with_context', static function ( $context, $result ) {
    if ( $context->payment_method === 'cod' && ! empty( $context->payment_data['vnap_test_payment_fail'] ) ) {
        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'vnap_test_payment_failure', 'Disposable payment failure; retry checkout.', 400 );
    }
}, 5, 2 );
add_action( 'woocommerce_blocks_checkout_block_registration', static function ( $registry ) {
    $registry->register( new class implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
        public function get_name() { return 'vnap-test-express'; }
        public function initialize() {
            wp_register_script( 'vnap-test-express', false, array( 'wc-blocks-registry','wp-element' ), false, true );
            wp_add_inline_script( 'vnap-test-express', "(function(){const h=wp.element.createElement;const button=h('button',{type:'button'},'VNAP express presence probe');wc.wcBlocksRegistry.registerExpressPaymentMethod({name:'vnap_test_express',paymentMethodId:'vnap_test_express',title:'VNAP express presence probe',content:button,edit:button,canMakePayment:()=>true,supports:{features:['products']}});})();" );
        }
        public function get_script_handles() { return array( 'vnap-test-express' ); }
        public function get_editor_script_handles() { return array(); }
        public function get_script_data() { return array(); }
    } );
} );
