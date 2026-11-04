<?php
namespace Tunlamweb\VNAddressPro\Admin;

use Tunlamweb\VNAddressPro\Migration\{MigrationService,ShippingConfiguration};

defined( 'ABSPATH' ) || exit;

final class ShippingConfigurationPage {
    private function post( string $key ): string {
        return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
    }
    public function authorize( string $command, string $nonce ): bool {
        return MigrationService::allowed() && in_array( $command, array( 'scan','apply' ), true ) && wp_verify_nonce( $nonce, 'vnap_shipping_' . $command ) !== false;
    }
    public function preflight(): void {
        if ( ( $_GET['tab'] ?? '' ) !== 'shipping' ) { return; }
        if ( ! MigrationService::allowed() || ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' && ! $this->authorize( $this->post( 'vnap_shipping_command' ), $this->post( '_wpnonce' ) ) ) ) {
            wp_die( esc_html__( 'Invalid configuration permission or nonce.', 'vn-address-pro' ), '', array( 'response' => 403 ) );
        }
    }
    private function begin( string $command ): void {
        echo '<form method="post" action="' . esc_url( admin_url( 'options-general.php?page=vn-address-pro&tab=shipping' ) ) . '"><input type="hidden" name="vnap_shipping_command" value="' . esc_attr( $command ) . '">';
        wp_nonce_field( 'vnap_shipping_' . $command );
    }
    public function render(): void {
        if ( ! MigrationService::allowed() ) { wp_die( esc_html__( 'Site and WooCommerce management permissions are required.', 'vn-address-pro' ), '', array( 'response' => 403 ) ); }
        $result = null; $command = $this->post( 'vnap_shipping_command' );
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' ) {
            if ( ! $this->authorize( $command, $this->post( '_wpnonce' ) ) ) { wp_die( esc_html__( 'Invalid configuration permission or nonce.', 'vn-address-pro' ), '', array( 'response' => 403 ) ); }
            $service = new ShippingConfiguration();
            $result = $command === 'scan' ? $service->scan() : $service->apply( $this->post( 'vnap_shipping_plan' ), isset( $_POST['selected'] ) && is_array( $_POST['selected'] ) ? wp_unslash( $_POST['selected'] ) : array(), $this->post( 'confirm' ) === '1' );
        }
        echo '<p>' . esc_html__( 'Review legacy province identifiers in shipping zones and the store address. Scanning saves no changes. Rates, methods, zone order, tax rules and customer/order addresses are preserved.', 'vn-address-pro' ) . '</p>';
        echo '<p>' . esc_html__( 'Name matches do not prove that historical boundaries are equivalent. Review geographic coverage and overlapping zones before selecting changes. Keep a database backup; automatic rollback is not provided.', 'vn-address-pro' ) . '</p>';
        if ( is_wp_error( $result ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'; }
        elseif ( is_array( $result ) && isset( $result['plan'] ) ) {
            echo '<h3>' . esc_html__( 'Preview — no changes saved', 'vn-address-pro' ) . '</h3>';
            if ( ! $result['plan']['items'] ) { echo '<p>' . esc_html__( 'No legacy Vietnamese province identifiers found in the scanned configuration.', 'vn-address-pro' ) . '</p>'; }
            else {
                $this->begin( 'apply' );
                echo '<input type="hidden" name="vnap_shipping_plan" value="' . esc_attr( $result['token'] ) . '"><table class="widefat striped"><thead><tr>';
                foreach ( array( __( 'Select', 'vn-address-pro' ), __( 'Configuration', 'vn-address-pro' ), __( 'Current identifier', 'vn-address-pro' ), __( 'Suggested identifier', 'vn-address-pro' ) ) as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; }
                echo '</tr></thead><tbody>';
                foreach ( $result['plan']['items'] as $index => $item ) {
                    echo '<tr><td><input type="checkbox" name="selected[]" value="' . esc_attr( $index ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'vn-address-pro' ), $item['label'] . ' ' . $item['before'] ) ) . '" ' . disabled( $item['after'] === null, true, false ) . '></td><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( $item['before'] ) . '</td><td>' . esc_html( $item['after'] ?? __( 'Unresolved — manual review required', 'vn-address-pro' ) ) . '</td></tr>';
                }
                echo '</tbody></table><p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'I reviewed the selected province mappings and geographic coverage, and request Apply.', 'vn-address-pro' ) . '</label></p>';
                submit_button( __( 'Apply selected configuration changes', 'vn-address-pro' ) ); echo '</form>';
            }
        } elseif ( is_array( $result ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Applied configuration changes: %d. Previously applied previews are not applied twice.', 'vn-address-pro' ), $result['applied'] ) ) . '</p></div>';
        }
        $this->begin( 'scan' ); submit_button( __( 'Scan configuration (dry run)', 'vn-address-pro' ), 'secondary' ); echo '</form>';
        echo '<h3>' . esc_html__( 'Recent configuration batches', 'vn-address-pro' ) . '</h3>';
        $history = get_option( ShippingConfiguration::HISTORY, array() );
        foreach ( is_array( $history ) ? array_slice( $history, 0, 5 ) : array() as $run ) {
            echo '<details><summary>' . esc_html( wp_date( 'Y-m-d H:i:s', $run['at'] ) . ' — ' . $run['applied'] ) . '</summary><pre>' . esc_html( wp_json_encode( $run['items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
        }
    }
}
