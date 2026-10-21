<?php
namespace Tunlamweb\VNAddressPro\Providers;

defined( 'ABSPATH' ) || exit;

final class ReliabilityPolicy {
    public const LKG_TTL = 7 * DAY_IN_SECONDS;
    public const NEGATIVE_TTL = 10 * MINUTE_IN_SECONDS;
    public const FAILURE_TTL = 30;
    public const LOCK_TTL = 30;
    public const RETRY_WINDOW = 0.5;

    public function ttl( string $kind ): int {
        $settings = array(
            'cache'         => array( DAY_IN_SECONDS, 60, DAY_IN_SECONDS ),
            'lkg'           => array( self::LKG_TTL, 2 * DAY_IN_SECONDS, 30 * DAY_IN_SECONDS ),
            'negative_cache' => array( self::NEGATIVE_TTL, 5 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS ),
            'failure'       => array( self::FAILURE_TTL, 15, 300 ),
            'refresh_lock'  => array( self::LOCK_TTL, 30, 60 ),
        );
        list( $default, $min, $max ) = $settings[ $kind ];
        $value = apply_filters( 'vnap_' . $kind . '_ttl', $default );
        return is_int( $value ) ? max( $min, min( $max, $value ) ) : $default;
    }

    public function max_attempts(): int {
        $value = apply_filters( 'vnap_provider_max_attempts', 2 );
        return is_int( $value ) ? max( 1, min( 2, $value ) ) : 2;
    }

    public function should_retry( \WP_Error $error, float $elapsed ): bool {
        $data = $error->get_error_data();
        $status = is_array( $data ) ? ( $data['upstream_status'] ?? 0 ) : 0;
        return $elapsed < self::RETRY_WINDOW && ( $error->get_error_code() === 'vnap_transport_error'
            || in_array( $status, array( 500, 502, 503, 504 ), true ) );
    }

    public function failure_ttl( \WP_Error $error ): int {
        $data = $error->get_error_data();
        if ( ! is_array( $data ) || ( $data['upstream_status'] ?? 0 ) !== 429 ) {
            return $this->ttl( 'failure' );
        }
        $header = $data['retry_after'] ?? '';
        $seconds = 60;
        if ( is_string( $header ) && ctype_digit( $header ) ) {
            $seconds = (int) $header;
        } elseif ( is_string( $header ) && $header !== '' ) {
            $date = strtotime( $header );
            $seconds = $date === false ? 60 : $date - time();
        }
        return max( 15, min( 300, $seconds ) );
    }
}
