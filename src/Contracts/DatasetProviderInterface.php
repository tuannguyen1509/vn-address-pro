<?php
namespace Tunlamweb\VNAddressPro\Contracts;
defined( 'ABSPATH' ) || exit;

interface DatasetProviderInterface {
    /** Complete canonical package: dataset + manifest; never a partial collection. */
    public function fetch_dataset(): array|\WP_Error;
    public function get_remote_version(): array|\WP_Error;
}
