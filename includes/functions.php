<?php
/** Internal namespace-only PSR-4 loader; no Composer install is needed. */
defined( 'ABSPATH' ) || exit;

function vnap_autoload( $class ) {
    $prefix = 'Tunlamweb\\VNAddressPro\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    // Only PHP class identifiers and namespace separators can become local paths.
    if ( ! preg_match( '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $relative ) ) {
        return;
    }
    $file = VNAP_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( is_file( $file ) ) {
        require_once $file;
    }
}

/** Return reusable fields; the consuming application owns submission and server validation. */
function vnap_render_address_fields( array $args = array() ): string {
    return \Tunlamweb\VNAddressPro\Frontend\AddressComponent::render( $args );
}
