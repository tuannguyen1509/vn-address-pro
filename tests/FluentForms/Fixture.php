<?php
/** Synthetic fixtures, disposable WordPress only. */
defined( 'ABSPATH' ) || exit;
function vnap_test_fluent_form( bool $required = true, array $override = array() ): object {
    $components = apply_filters( 'fluentform/editor_components', array( 'general' => array(), 'advanced' => array() ), 0 );
    $types = array(); foreach ( $components['advanced'] as $component ) { $types[$component['element']] = $component; }
    $fields = array();
    foreach ( array( 'home', 'office' ) as $group ) {
        foreach ( array( 'province', 'ward' ) as $kind ) {
            $item = $types['vnap_' . $kind]; $item['attributes']['name'] = $group . '_' . $kind;
            $item['settings']['validation_rules']['required']['value'] = $required && $group === 'home';
            $item['settings']['vnap_province_field'] = $group . '_province';
            $item['uniqElKey'] = 'vnap_test_' . $group . '_' . $kind; $fields[] = $item;
        }
    }
    $fields = $override ?: $fields;
    if ( ! array_filter( $fields, static fn( $field ) => ( $field['attributes']['name'] ?? '' ) === 'contact_name' ) ) { $fields[] = array( 'element' => 'input_text', 'attributes' => array( 'name' => 'contact_name', 'type' => 'text', 'class' => '', 'id' => '', 'value' => '', 'placeholder' => '' ),
        'settings' => array( 'label' => 'Synthetic contact', 'label_placement' => '', 'container_class' => '', 'help_message' => '', 'admin_field_label' => '', 'conditional_logics' => array(), 'validation_rules' => array( 'required' => array( 'value' => true, 'message' => 'Synthetic contact required' ) ) ),
        'editor_options' => array( 'title' => 'Simple Text', 'template' => 'inputText', 'icon_class' => 'ff-edit-text' ), 'uniqElKey' => 'vnap_test_contact' ); }
    $definition = array( 'fields' => $fields, 'submitButton' => array( 'element' => 'button', 'attributes' => array( 'type' => 'submit', 'class' => '' ),
        'settings' => array( 'align' => 'left', 'button_style' => 'default', 'container_class' => '', 'button_size' => 'md', 'button_ui' => array( 'type' => 'default', 'text' => 'Send', 'img_url' => '' ) ), 'editor_options' => array( 'title' => 'Submit Button' ) ) );
    $id = wpFluent()->table( 'fluentform_forms' )->insertGetId( array( 'title' => 'Synthetic VNAP Fluent Form', 'status' => 'published', 'form_fields' => wp_json_encode( $definition ), 'created_by' => 1, 'has_payment' => 0, 'type' => 'form', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
    $settings = \FluentForm\App\Models\Form::getFormsDefaultSettings();
    $settings['confirmation']['samePageFormBehavior'] = 'reset_form';
    wpFluent()->table( 'fluentform_form_meta' )->insert( array( 'form_id' => $id, 'meta_key' => 'formSettings', 'value' => wp_json_encode( $settings ) ) );
    $notification = array( 'name' => 'Synthetic notification', 'enabled' => true, 'sendTo' => array( 'type' => 'email', 'email' => 'receiver@example.invalid' ),
        'subject' => 'Synthetic address', 'message' => '{inputs.home_province}|{inputs.home_ward}|{inputs.home_province.value}|{inputs.home_ward.value}', 'fromName' => 'Test', 'fromEmail' => 'sender@example.invalid', 'replyTo' => '', 'bcc' => '', 'conditionals' => array( 'status' => false ), 'email_template' => '' );
    wpFluent()->table( 'fluentform_form_meta' )->insert( array( 'form_id' => $id, 'meta_key' => 'notifications', 'value' => wp_json_encode( $notification ) ) );
    return \FluentForm\App\Models\Form::find( $id );
}
function vnap_test_fluent_delete( object $form ): void {
    foreach ( array( 'fluentform_entry_details', 'fluentform_submission_meta', 'fluentform_submissions', 'fluentform_form_meta' ) as $table ) {
        wpFluent()->table( $table )->where( 'form_id', $form->id )->delete();
    }
    wpFluent()->table( 'fluentform_forms' )->where( 'id', $form->id )->delete();
}
