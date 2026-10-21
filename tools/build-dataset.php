<?php
/** Development/build time only: wp eval-file tools/build-dataset.php [output-root]. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

$vnap_response = wp_remote_get( \Tunlamweb\VNAddressPro\Providers\ProvinceOpenApiProvider::UPSTREAM . '/?depth=2', array( 'timeout' => 60 ) );
if ( is_wp_error( $vnap_response ) || wp_remote_retrieve_response_code( $vnap_response ) !== 200 ) {
    WP_CLI::error( 'Build-time source request failed.' );
}
$vnap_source = json_decode( wp_remote_retrieve_body( $vnap_response ), true );
if ( ! is_array( $vnap_source ) || ! ( new \Tunlamweb\VNAddressPro\Providers\Validation\ProvinceOpenApiValidator() )->validate( $vnap_source ) ) {
    WP_CLI::error( 'Build-time source structure is invalid.' );
}
$vnap_version = gmdate( 'YmdHis' );
$vnap_dataset = array( 'schema_version' => 1, 'dataset_version' => $vnap_version, 'provinces' => array() );
$vnap_ward_count = 0;
foreach ( $vnap_source as $vnap_province ) {
    if ( ! isset( $vnap_province['wards'] ) || ! is_array( $vnap_province['wards'] ) ) {
        WP_CLI::error( 'Build-time ward collection is missing.' );
    }
    $vnap_record = array_intersect_key( $vnap_province, array_flip( array( 'code', 'name', 'division_type', 'codename', 'phone_code', 'wards' ) ) );
    $vnap_record['wards'] = array_map( static function ( $ward ) use ( $vnap_record ) {
        $ward = array_intersect_key( $ward, array_flip( array( 'code', 'name', 'division_type', 'codename', 'province_code' ) ) );
        // The root endpoint nests wards under their parent but omits province_code.
        $ward['province_code'] = $ward['province_code'] ?? $vnap_record['code'];
        return $ward;
    }, $vnap_record['wards'] );
    $vnap_ward_count += count( $vnap_record['wards'] );
    $vnap_dataset['provinces'][] = $vnap_record;
}
if ( ! ( new \Tunlamweb\VNAddressPro\Data\DatasetValidator() )->validate( $vnap_dataset ) ) {
    WP_CLI::error( 'Build-time dataset validation failed.' );
}
$vnap_json = wp_json_encode( $vnap_dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
$vnap_checksum = hash( 'sha256', $vnap_json );
$vnap_manifest = array(
    'schema_version' => 1, 'dataset_version' => $vnap_version,
    'source' => 'Province Open API v2', 'source_url' => 'https://provinces.open-api.vn/api/v2/?depth=2',
    'source_project' => 'https://github.com/hongquan/vn-open-api-provinces',
    'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'province_count' => count( $vnap_dataset['provinces'] ),
    'ward_count' => $vnap_ward_count, 'checksum' => $vnap_checksum,
);
$vnap_manifest['generated_at'] = \DateTimeImmutable::createFromFormat( '!YmdHis', $vnap_version, new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
$vnap_output = $args[0] ?? VNAP_PATH;
wp_mkdir_p( $vnap_output . '/data' );
wp_mkdir_p( $vnap_output . '/src/Data' );
file_put_contents( $vnap_output . '/data/vn-address.json', $vnap_json );
file_put_contents( $vnap_output . '/data/manifest.json', wp_json_encode( $vnap_manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
$vnap_php = "<?php\nnamespace Tunlamweb\\VNAddressPro\\Data;\n\ndefined( 'ABSPATH' ) || exit;\n\n/** Generated with the bundle; no JSON parsing needed for ordinary version checks. */\nfinal class BundledDataset {\n    public const VERSION = '$vnap_version';\n    public const CHECKSUM = '$vnap_checksum';\n}\n";
file_put_contents( $vnap_output . '/src/Data/BundledDataset.php', $vnap_php );
WP_CLI::line( wp_json_encode( $vnap_manifest, JSON_UNESCAPED_SLASHES ) );
