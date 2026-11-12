<?php
namespace Tunlamweb\VNAddressPro\Data;
defined( 'ABSPATH' ) || exit;

/** Bounded administrative counts only; no customer/order data or historical inference. */
final class DatasetDiff {
    public function compare( array $before, array $after ): array {
        $index = static function ( array $dataset ): array {
            $p = array(); $w = array();
            foreach ( $dataset['provinces'] as $province ) {
                $p[ $province['code'] ] = $province['name'];
                foreach ( $province['wards'] as $ward ) { $w[ $ward['code'] ] = array( 'name' => $ward['name'], 'parent' => $province['code'] ); }
            }
            return array( $p, $w );
        };
        [ $old_p, $old_w ] = $index( $before ); [ $new_p, $new_w ] = $index( $after );
        $renamed_p = 0; $renamed_w = 0; $moved = 0;
        foreach ( array_intersect_key( $new_p, $old_p ) as $code => $name ) { $renamed_p += $name !== $old_p[ $code ] ? 1 : 0; }
        foreach ( array_intersect_key( $new_w, $old_w ) as $code => $ward ) {
            $renamed_w += $ward['name'] !== $old_w[ $code ]['name'] ? 1 : 0;
            $moved += $ward['parent'] !== $old_w[ $code ]['parent'] ? 1 : 0;
        }
        return array( 'provinces_before' => count( $old_p ), 'provinces_after' => count( $new_p ), 'wards_before' => count( $old_w ), 'wards_after' => count( $new_w ),
            'provinces_added' => count( array_diff_key( $new_p, $old_p ) ), 'provinces_removed' => count( array_diff_key( $old_p, $new_p ) ), 'provinces_renamed' => $renamed_p,
            'wards_added' => count( array_diff_key( $new_w, $old_w ) ), 'wards_removed' => count( array_diff_key( $old_w, $new_w ) ), 'wards_renamed' => $renamed_w, 'wards_reparented' => $moved );
    }
}
