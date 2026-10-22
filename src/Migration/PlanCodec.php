<?php
namespace Tunlamweb\VNAddressPro\Migration;
defined( 'ABSPATH' ) || exit;

/** Bounded stateless preview: no option/transient/session write, never put in a URL. */
final class PlanCodec {
    public const VERSION = 1;
    public const TTL = 1200;
    public const MAX_BYTES = 524288;
    public function encode( array $plan ): string|\WP_Error {
        $json = wp_json_encode( $plan );
        if ( ! is_string( $json ) || strlen( $json ) > self::MAX_BYTES ) { return new \WP_Error( 'vnap_plan_size', __( 'Preview is too large. Reduce the batch size.', 'vn-address-pro' ) ); }
        $body = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
        return $body . '.' . hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
    }
    public function decode( string $token ): array|\WP_Error {
        if ( strlen( $token ) <= self::MAX_BYTES * 1.4 ) {
            $parts = explode( '.', $token );
            if ( count( $parts ) === 2 && hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) {
                $json = base64_decode( strtr( $parts[0], '-_', '+/' ), true ); $p = is_string( $json ) ? json_decode( $json, true, 40 ) : null;
                if ( is_array( $p ) && ( $p['version'] ?? null ) === self::VERSION && ( $p['user'] ?? null ) === get_current_user_id() && ( $p['site'] ?? null ) === get_current_blog_id()
                    && is_int( $p['expires'] ?? null ) && $p['expires'] > time() && $p['expires'] <= time() + self::TTL
                    && is_string( $p['id'] ?? null ) && preg_match( '/\A[a-f0-9-]{36}\z/', $p['id'] ) && is_array( $p['items'] ?? null ) && count( $p['items'] ) <= 100 ) { return $p; }
            }
        }
        return new \WP_Error( 'vnap_plan_invalid', __( 'Preview is invalid, expired or belongs to another user/site. Scan again.', 'vn-address-pro' ) );
    }
}
