<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\FluentForms;

use Tunlamweb\VNAddressPro\Integrations\Forms\Contracts\FormAdapterInterface;
defined( 'ABSPATH' ) || exit;

/** Optional native Fluent Forms adapter. Vendor classes are loaded only after detection. */
final class FluentFormsIntegration implements FormAdapterInterface {
    // Essential-input parsing excludes inactive fields/containers before custom validation.
    public const MIN_VERSION = '6.2.0';
    public const MIN_WORDPRESS = '6.4';
    private bool $booted = false;
    public function get_id(): string { return 'fluent-forms'; }
    public function is_available(): bool {
        global $wp_version;
        return version_compare( (string) $wp_version, self::MIN_WORDPRESS, '>=' )
            && defined( 'FLUENTFORM_VERSION' ) && version_compare( FLUENTFORM_VERSION, self::MIN_VERSION, '>=' )
            && function_exists( 'wpFluentForm' )
            && class_exists( '\FluentForm\App\Services\FormBuilder\BaseFieldManager' )
            && class_exists( '\FluentForm\App\Modules\Form\FormFieldsParser' )
            && method_exists( '\FluentForm\App\Services\Parser\Form', 'getEssentialInputs' );
    }
    public function status_details(): array {
        return array( 'detected' => defined( 'FLUENTFORM_VERSION' ), 'version' => defined( 'FLUENTFORM_VERSION' ) ? FLUENTFORM_VERSION : '' );
    }
    public function boot(): void {
        if ( $this->booted || ! $this->is_available() ) { return; }
        $this->booted = true;
        $fields = static function (): void { new AddressSelect( 'province' ); new AddressSelect( 'ward' ); };
        if ( did_action( 'init' ) ) { $fields(); } else { add_action( 'init', $fields, 5 ); }
        $validator = new Validator(); $formatter = new Formatter();
        foreach ( array( 'vnap_province', 'vnap_ward' ) as $key ) {
            add_filter( 'fluentform/validate_input_item_' . $key, array( $validator, 'validate' ), 20, 5 );
            add_filter( 'fluentform/response_render_' . $key, array( $formatter, 'format' ), 20, 4 );
        }
    }
}
