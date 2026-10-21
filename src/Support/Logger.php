<?php
namespace Tunlamweb\VNAddressPro\Support;

defined( 'ABSPATH' ) || exit;

final class Logger {
    public function event( string $event, array $context = array() ): void {
        // Never accept payloads, arbitrary messages, URLs, addresses or user data.
        $context = array_intersect_key( $context, array_flip( array( 'resource', 'error_code', 'classification', 'status' ) ) );
        do_action( 'vnap_diagnostic', $event, $context );
        $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
        if ( $debug && apply_filters( 'vnap_log_to_error_log', true ) === true ) {
            error_log( '[VN Address Pro diagnostic] ' . wp_json_encode( array( 'event' => $event, 'context' => $context ) ) );
        }
    }
}
