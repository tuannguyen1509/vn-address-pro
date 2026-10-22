<?php
namespace Tunlamweb\VNAddressPro\Admin;
use Tunlamweb\VNAddressPro\Migration\{MigrationService,MigrationAudit};
defined( 'ABSPATH' ) || exit;

/** Protected same-page POSTs. No JS/CSS bundle or persisted preview is needed. */
final class MigrationPage {
    /** Reject protected POSTs before WordPress sends the admin page headers. */
    public function preflight(): void {
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        if ( $tab !== 'migration' ) { return; }
        if ( ! MigrationService::allowed() || ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' && ! $this->authorize( $this->post( 'vnap_migration_command' ), $this->post( '_wpnonce' ) ) ) ) {
            wp_die( esc_html__( 'Invalid migration permission or nonce.', 'vn-address-pro' ), '', array( 'response' => 403 ) );
        }
    }
    public function authorize( string $command, string $nonce ): bool {
        return MigrationService::allowed() && in_array( $command, array( 'scan','apply' ), true ) && wp_verify_nonce( $nonce, 'vnap_migration_' . $command ) !== false;
    }
    private function post( string $key ): string { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; }
    public function render(): void {
        if ( ! MigrationService::allowed() ) { wp_die( esc_html__( 'Migration requires site and WooCommerce management permissions.', 'vn-address-pro' ), '', array( 'response' => 403 ) ); }
        $service = new MigrationService(); $result = null; $command = $this->post( 'vnap_migration_command' );
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' ) {
            if ( ! $this->authorize( $command, $this->post( '_wpnonce' ) ) ) { wp_die( esc_html__( 'Invalid migration permission or nonce.', 'vn-address-pro' ), '', array( 'response' => 403 ) ); }
            if ( $command === 'scan' ) {
                $scope = array();
                foreach ( array( 'mode','addresses','batch','page','from','to','days' ) as $key ) { $input = $key === 'page' ? 'vnap_page' : $key; if ( isset( $_POST[ $input ] ) ) { $scope[ $key ] = is_string( $_POST[ $input ] ) ? wp_unslash( $_POST[ $input ] ) : array(); } }
                $scope['order_display'] = $this->post( 'order_display' ) === '1';
                $result = $service->scan( $scope );
            } else {
                $selected = isset( $_POST['selected'] ) && is_array( $_POST['selected'] ) ? wp_unslash( $_POST['selected'] ) : array();
                $result = $service->apply( $this->post( 'vnap_plan' ), $selected, $this->post( 'confirm' ) === '1', $this->post( 'confirm_orders' ) === '1', $this->post( 'medium' ) === '1' );
            }
        }
        echo '<section class="vnap-migration"><p>' . esc_html__( 'This tool can modify customer and historical order addresses. Always review the preview before applying.', 'vn-address-pro' ) . '</p>';
        if ( is_wp_error( $result ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_code() . ': ' . $result->get_error_message() ) . '</p></div>'; }
        elseif ( is_array( $result ) && isset( $result['plan'] ) ) { $this->preview( $result ); }
        elseif ( is_array( $result ) ) { $this->run( $result ); }
        $this->scan_form();
        echo '<h3>' . esc_html__( 'Recent applied batches (rollback foundation)', 'vn-address-pro' ) . '</h3><p>' . esc_html__( 'The latest 20 batches retain before/after administrative fields. Rollback execution and exports are deferred.', 'vn-address-pro' ) . '</p>';
        foreach ( array_slice( ( new MigrationAudit() )->recent(), 0, 5 ) as $run ) { $this->run( $run, false ); }
        echo '</section>';
    }
    private function begin( string $command ): void {
        echo '<form method="post" action="' . esc_url( admin_url( 'options-general.php?page=vn-address-pro&tab=migration' ) ) . '"><input type="hidden" name="vnap_migration_command" value="' . esc_attr( $command ) . '">';
        wp_nonce_field( 'vnap_migration_' . $command );
    }
    private function scan_form( array $scope = array() ): void {
        $scope = $scope + array( 'mode' => 'customers', 'addresses' => 'both', 'batch' => 25, 'page' => 1, 'from' => '', 'to' => '', 'order_display' => false );
        echo '<h3>' . esc_html__( 'Scan scope / Dry run', 'vn-address-pro' ) . '</h3>'; $this->begin( 'scan' );
        echo '<p><label><input type="checkbox" checked disabled> ' . esc_html__( 'Dry run: scan and preview perform no writes.', 'vn-address-pro' ) . '</label></p>';
        foreach ( array( 'mode' => array( 'customers' => __( 'Customers only', 'vn-address-pro' ), 'orders' => __( 'Orders only', 'vn-address-pro' ), 'both' => __( 'Customers + Orders', 'vn-address-pro' ) ), 'addresses' => array( 'both' => __( 'Billing + Shipping', 'vn-address-pro' ), 'billing' => __( 'Billing', 'vn-address-pro' ), 'shipping' => __( 'Shipping', 'vn-address-pro' ) ) ) as $name => $options ) {
            echo '<p><label>' . esc_html( $name === 'mode' ? __( 'Source', 'vn-address-pro' ) : __( 'Address type', 'vn-address-pro' ) ) . ' <select name="' . esc_attr( $name ) . '">';
            foreach ( $options as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $scope[ $name ], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select></label></p>';
        }
        echo '<p><label>' . esc_html__( 'Batch size (maximum 50 entities)', 'vn-address-pro' ) . ' <input type="number" name="batch" min="1" max="50" value="' . esc_attr( $scope['batch'] ) . '"></label> <input type="hidden" name="vnap_page" value="' . esc_attr( $scope['page'] ) . '"></p>';
        echo '<p><label>' . esc_html__( 'Orders from', 'vn-address-pro' ) . ' <input type="date" name="from" value="' . esc_attr( $scope['from'] ) . '"></label> <label>' . esc_html__( 'through', 'vn-address-pro' ) . ' <input type="date" name="to" value="' . esc_attr( $scope['to'] ) . '"></label></p>';
        echo '<p><label>' . esc_html__( 'Or last N calendar days including today (orders)', 'vn-address-pro' ) . ' <input type="number" name="days" min="1" max="36500"></label></p>';
        echo '<p><label><input type="checkbox" name="order_display" value="1" ' . checked( $scope['order_display'], true, false ) . '> ' . esc_html__( 'Also canonicalize selected historical order state/city/name snapshots. Default: codes only.', 'vn-address-pro' ) . '</label></p>';
        submit_button( $scope['page'] > 1 ? __( 'Scan next batch (dry run)', 'vn-address-pro' ) : __( 'Scan (dry run)', 'vn-address-pro' ) ); echo '</form>';
    }
    private function value( $value ): string { return esc_html( is_array( $value ) ? (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) : (string) $value ); }
    private function candidates( ?array $match ): string {
        return $this->value( implode( ', ', array_column( $match['candidates'] ?? array(), 'name' ) ) );
    }
    private function preview( array $result ): void {
        $plan = $result['plan'];
        echo '<h3>' . esc_html__( 'Preview — no changes saved', 'vn-address-pro' ) . '</h3><p>' . esc_html__( 'Confidence: HIGH only by default. Nothing is selected automatically. Low/ambiguous results cannot be applied.', 'vn-address-pro' ) . '</p><p>' . esc_html__( 'Dataset version:', 'vn-address-pro' ) . ' ' . esc_html( $plan['dataset_version'] ) . '</p>';
        $this->begin( 'apply' ); echo '<input type="hidden" name="vnap_plan" value="' . esc_attr( $result['token'] ) . '"><table class="widefat striped"><thead><tr>';
        foreach ( array( __( 'Select', 'vn-address-pro' ), __( 'Type / ID / Address', 'vn-address-pro' ), __( 'Current state / city / codes', 'vn-address-pro' ), __( 'Matched province / ward', 'vn-address-pro' ), __( 'Confidence / Status', 'vn-address-pro' ), __( 'Warnings / Changes', 'vn-address-pro' ) ) as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $plan['items'] as $i => $r ) {
            $eligible = in_array( $r['status'], array( 'MIGRATABLE_HIGH','MIGRATABLE_MEDIUM' ), true );
            echo '<tr data-vnap-migration-index="' . esc_attr( $i ) . '"><td><input type="checkbox" name="selected[]" value="' . esc_attr( $i ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %1$s %2$d %3$s', 'vn-address-pro' ), $r['source_type'], $r['entity_id'], $r['address_type'] ) ) . '" ' . disabled( $eligible, false, false ) . '></td><td>' . $this->value( $r['source_type'] . ' / ' . $r['entity_id'] . ' / ' . $r['address_type'] ) . '</td><td>' . $this->value( $r['original']['state'] ) . '<br>' . $this->value( $r['original']['city'] ) . '<br>' . $this->value( array( $r['original']['province_meta'], $r['original']['ward_meta'] ) ) . '</td><td>' . $this->candidates( $r['province_candidate'] ) . '<br>' . $this->candidates( $r['ward_candidate'] ) . '</td><td>' . $this->value( $r['overall_confidence'] . ' / ' . $r['status'] ) . '<br>' . $this->value( $r['province_confidence'] . ' / ' . $r['ward_confidence'] ) . '</td><td>' . $this->value( implode( ', ', $r['warnings'] ) );
            echo '<details><summary>' . esc_html__( 'Before → After', 'vn-address-pro' ) . '</summary><pre>' . $this->value( $r['proposed_changes'] ) . '</pre></details></td></tr>';
        }
        echo '</tbody></table><p><label><input type="checkbox" name="medium" value="1"> ' . esc_html__( 'Also allow reviewed MEDIUM confidence selections.', 'vn-address-pro' ) . '</label></p><p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'I reviewed the selected changes and request Apply.', 'vn-address-pro' ) . '</label></p><p><label><input type="checkbox" name="confirm_orders" value="1"> ' . esc_html__( 'I understand selected historical orders will be modified.', 'vn-address-pro' ) . '</label></p>';
        submit_button( __( 'Apply selected changes', 'vn-address-pro' ) ); echo '</form>';
        if ( $plan['more'] ) { $scope = $plan['scope']; ++$scope['page']; $this->scan_form( $scope ); }
    }
    private function run( array $run, bool $items = true ): void {
        echo '<div class="vnap-migration-run"><h3>' . esc_html__( 'Migration batch', 'vn-address-pro' ) . ' ' . esc_html( $run['id'] ) . '</h3><p>' . esc_html( wp_date( 'Y-m-d H:i:s', $run['at'] ) ) . '</p><pre>' . $this->value( array_intersect_key( $run, array_flip( array( 'scanned','proposed','applied','skipped','failed','replayed' ) ) ) ) . '</pre>';
        if ( $items ) { foreach ( $run['items'] as $r ) { echo '<p>' . $this->value( $r['source_type'] . ' / ' . $r['entity_id'] . ' / ' . $r['address_type'] . ': ' . $r['status'] ) . '</p>'; } }
        echo '</div>';
    }
}
