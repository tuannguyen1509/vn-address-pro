<?php
namespace Tunlamweb\VNAddressPro\Tests\Support;

defined( 'ABSPATH' ) || exit;

/** Disable local selection only in tests explicitly exercising the old remote path. */
final class LocalState {
    private $metadata;

    public function __construct() {
        $this->metadata = get_option( 'vnap_dataset_metadata', false );
        delete_option( 'vnap_dataset_metadata' );
    }

    public function restore(): void {
        if ( $this->metadata === false ) {
            delete_option( 'vnap_dataset_metadata' );
        } else {
            update_option( 'vnap_dataset_metadata', $this->metadata, false );
        }
    }
}
