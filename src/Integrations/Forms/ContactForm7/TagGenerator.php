<?php
namespace Tunlamweb\VNAddressPro\Integrations\Forms\ContactForm7;
defined( 'ABSPATH' ) || exit;

/** Uses each supported CF7 generation's native generator, with no custom admin JS. */
final class TagGenerator {
    public function register(): void {
        if ( ! class_exists( '\WPCF7_TagGenerator' ) ) { return; }
        foreach ( array( 'province' => __( 'VN Province', 'vn-address-pro' ), 'ward' => __( 'VN Ward', 'vn-address-pro' ), 'address' => __( 'VN Address line', 'vn-address-pro' ) ) as $kind => $title ) {
            \WPCF7_TagGenerator::get_instance()->add( 'vnap_' . $kind, $title, array( $this, 'render' ), array( 'version' => class_exists( '\WPCF7_TagGeneratorGenerator' ) ? '2' : '1' ) );
        }
    }
    public function render( $form, array $options ): void {
        $type = $options['id'];
        echo '<header class="description-box"><h3>' . esc_html__( 'VN Address field', 'vn-address-pro' ) . '</h3><p>' . esc_html__( 'Use unique names. Ward must reference the exact name of its Province field. Ordinary mail-tags display names; [_vnap_code_FIELD] displays the canonical code.', 'vn-address-pro' ) . '</p></header><div class="control-box">';
        if ( class_exists( '\WPCF7_TagGeneratorGenerator' ) ) {
            $generator = new \WPCF7_TagGeneratorGenerator( $options['content'] );
            $generator->print( 'field_type', array( 'with_required' => true, 'select_options' => array( $type => __( 'Address field', 'vn-address-pro' ) ) ) );
            $generator->print( 'field_name' );
            if ( $type === 'vnap_ward' ) { echo '<p><label>' . esc_html__( 'Province field name', 'vn-address-pro' ) . ' <input type="text" data-tag-part="option" data-tag-option="province:" pattern="[A-Za-z][A-Za-z0-9_\-]*"></label></p>'; }
            if ( $type === 'vnap_address' ) { $generator->print( 'default_value', array( 'with_placeholder' => true ) ); }
            echo '</div><footer class="insert-box">'; $generator->print( 'insert_box_content' ); $generator->print( 'mail_tag_tip' ); echo '</footer>';
        } else {
            echo '<p><label><input type="checkbox" name="required"> ' . esc_html__( 'Required field', 'vn-address-pro' ) . '</label></p><p><label>' . esc_html__( 'Field name', 'vn-address-pro' ) . ' <input type="text" name="name" class="tg-name oneline"></label></p>';
            if ( $type === 'vnap_ward' ) { echo '<p><label>' . esc_html__( 'Province field name', 'vn-address-pro' ) . ' <input type="text" name="province" class="option oneline"></label></p>'; }
            echo '</div><div class="insert-box"><input type="text" name="' . esc_attr( $type ) . '" class="tag code" readonly><div class="submitbox"><input type="button" class="button button-primary insert-tag" value="' . esc_attr__( 'Insert Tag', 'vn-address-pro' ) . '"></div></div>';
        }
    }
}
