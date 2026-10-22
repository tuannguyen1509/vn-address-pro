<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7;
use Tunlamweb\VNAddressPro\Integrations\Forms\Contracts\FormAdapterInterface;
defined( 'ABSPATH' ) || exit;

final class ContactForm7Integration implements FormAdapterInterface {
    public const MIN_VERSION = '5.8.1';
    private bool $booted = false;
    public function get_id(): string { return 'contact-form-7'; }
    public function is_available(): bool {
        return defined( 'WPCF7_VERSION' ) && version_compare( WPCF7_VERSION, self::MIN_VERSION, '>=' )
            && function_exists( 'wpcf7_add_form_tag' ) && function_exists( 'wpcf7_form_controls_class' ) && function_exists( 'wpcf7_format_atts' )
            && function_exists( 'wpcf7_get_validation_error' ) && function_exists( 'wpcf7_get_hangover' )
            && class_exists( '\WPCF7_ContactForm' ) && class_exists( '\WPCF7_FormTag' ) && class_exists( '\WPCF7_Submission' ) && class_exists( '\WPCF7_Validation' );
    }
    public function boot(): void {
        if ( $this->booted || ! $this->is_available() ) { return; } $this->booted = true;
        add_action( 'wpcf7_init', array( new FormTags(), 'register' ) );
        $validator = new Validator();
        foreach ( FormTags::TYPES as $type ) { add_filter( 'wpcf7_validate_' . $type, array( $validator, 'validate' ), 20, 2 ); }
        add_filter( 'wpcf7_mail_tag_replaced', array( new MailTags(), 'replace' ), 20, 4 );
        add_action( 'wpcf7_admin_init', array( new TagGenerator(), 'register' ), 20 );
    }
}
