<?php
namespace Tunlamweb\VNAddressPro\Admin;
use Tunlamweb\VNAddressPro\Data\{DatasetMetadata,DatasetIntegrityAuditor,BundledDataset};
use Tunlamweb\VNAddressPro\Sync\{DatasetSyncService,DatasetScheduler,DatasetSyncLock,RemoteCacheCleaner};
use Tunlamweb\VNAddressPro\Repository\LocalAddressRepository;
defined( 'ABSPATH' ) || exit;

/** Native, synchronous admin-post UI. No scripts/styles are loaded globally. */
final class SettingsPage {
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_vnap_dataset_action', array( $this, 'handle' ) );
        add_action( 'admin_notices', array( $this, 'notice' ) );
    }
    public function menu(): void { add_options_page( __( 'VN Address', 'vn-address-pro' ), __( 'VN Address', 'vn-address-pro' ), 'manage_options', 'vn-address-pro', array( $this, 'render' ) ); }
    public function authorize( string $command, string $nonce ): bool {
        return current_user_can( 'manage_options' ) && wp_verify_nonce( $nonce, 'vnap_' . $command ) !== false;
    }
    public function handle(): void {
        $command = isset( $_POST['vnap_command'] ) && is_string( $_POST['vnap_command'] ) ? sanitize_key( wp_unslash( $_POST['vnap_command'] ) ) : '';
        $nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
        if ( ! $this->authorize( $command, $nonce ) || ! in_array( $command, array( 'schedule','check','sync','validate','repair','reinstall','clear' ), true ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this dataset action.', 'vn-address-pro' ), '', array( 'response' => 403 ) );
        }
        $service = new DatasetSyncService();
        switch ( $command ) {
            case 'schedule':
                $value = isset( $_POST['vnap_schedule'] ) && is_string( $_POST['vnap_schedule'] ) ? wp_unslash( $_POST['vnap_schedule'] ) : 'disabled';
                update_option( DatasetMetadata::SCHEDULE, DatasetScheduler::sanitize( $value ), false );
                ( new DatasetScheduler() )->reconcile();
                $message = __( 'Update schedule saved.', 'vn-address-pro' ); break;
            case 'check': $message = $service->check()->message(); break;
            case 'sync': $message = $service->sync()->message(); break;
            case 'repair': $message = $service->repair()->message(); break;
            case 'reinstall': $message = $service->reinstall( isset( $_POST['vnap_confirm_downgrade'] ) && $_POST['vnap_confirm_downgrade'] === '1' )->message(); break;
            case 'validate':
                $report = ( new DatasetIntegrityAuditor() )->audit();
                update_option( DatasetMetadata::AUDIT, $report, false );
                $message = $report['healthy'] ? __( 'Dataset validation passed.', 'vn-address-pro' ) : __( 'Integrity warning. Review the validation report in Tools.', 'vn-address-pro' ); break;
            default:
                ( new RemoteCacheCleaner() )->clear();
                $message = __( 'Remote active, negative and failure caches cleared for installed province codes. Last-known-good data was retained.', 'vn-address-pro' );
        }
        set_transient( 'vnap_admin_result_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( admin_url( 'options-general.php?page=vn-address-pro&tab=' . ( $command === 'schedule' ? 'general' : ( in_array( $command, array( 'sync','check' ), true ) ? 'data' : 'tools' ) ) ) );
        exit;
    }
    public function notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $audit = get_option( DatasetMetadata::AUDIT, array() );
        if ( ( is_array( $audit ) && isset( $audit['healthy'] ) && ! $audit['healthy'] ) || ! ( new LocalAddressRepository() )->has_usable_dataset() ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'VN Address Pro dataset requires attention. Open Settings → VN Address → Tools.', 'vn-address-pro' ) . '</p></div>';
        }
    }
    private function rows( array $rows ): void {
        echo '<table class="widefat striped vnap-status"><tbody>';
        foreach ( $rows as $label => $value ) { echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( is_scalar( $value ) ? (string) $value : __( 'Invalid metadata', 'vn-address-pro' ) ) . '</td></tr>'; }
        echo '</tbody></table>';
    }
    private function date( $timestamp ): string { return is_int( $timestamp ) && $timestamp > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : __( 'Not recorded', 'vn-address-pro' ); }
    private function form( string $command, string $label, bool $downgrade = false ): void {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="vnap-action">';
        echo '<input type="hidden" name="action" value="vnap_dataset_action"><input type="hidden" name="vnap_command" value="' . esc_attr( $command ) . '">';
        wp_nonce_field( 'vnap_' . $command );
        if ( $downgrade ) { echo '<p><label><input type="checkbox" name="vnap_confirm_downgrade" value="1"> ' . esc_html__( 'I understand that reinstalling the bundle will downgrade this newer dataset.', 'vn-address-pro' ) . '</label></p>'; }
        submit_button( $label, 'secondary', 'submit', true, ( new DatasetSyncLock() )->active() ? array( 'disabled' => 'disabled' ) : array() );
        echo '</form>';
    }
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'vn-address-pro' ), '', array( 'response' => 403 ) ); }
        $tabs = array( 'general' => __( 'General', 'vn-address-pro' ), 'data' => __( 'Data', 'vn-address-pro' ), 'tools' => __( 'Tools', 'vn-address-pro' ), 'status' => __( 'System Status', 'vn-address-pro' ) );
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'general'; }
        $meta = new DatasetMetadata(); $m = $meta->read(); $s = $meta->state(); $last = $s['last_attempt'] ?? array();
        echo '<div class="wrap vnap-admin"><h1>' . esc_html__( 'VN Address', 'vn-address-pro' ) . '</h1><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'VN Address settings', 'vn-address-pro' ) . '">';
        foreach ( $tabs as $key => $label ) { echo '<a class="nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'options-general.php?page=vn-address-pro&tab=' . $key ) ) . '"' . ( $tab === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; }
        echo '</nav><h2>' . esc_html( $tabs[ $tab ] ) . '</h2>';
        $result = get_transient( 'vnap_admin_result_' . get_current_user_id() );
        if ( is_string( $result ) ) { echo '<div class="notice notice-info"><p>' . esc_html( $result ) . '</p></div>'; delete_transient( 'vnap_admin_result_' . get_current_user_id() ); }
        $base = array(
            __( 'Plugin Version', 'vn-address-pro' ) => VNAP_VERSION,
            __( 'Address Model', 'vn-address-pro' ) => __( 'Vietnam Two-Level Administration', 'vn-address-pro' ),
            __( 'Dataset Version', 'vn-address-pro' ) => $m['dataset_version'] ?? '',
            __( 'Database Schema Version', 'vn-address-pro' ) => get_option( 'vnap_db_version', '' ),
        );
        if ( $tab === 'general' ) {
            $this->rows( $base );
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vnap_dataset_action"><input type="hidden" name="vnap_command" value="schedule">';
            wp_nonce_field( 'vnap_schedule' );
            echo '<p><label for="vnap-schedule">' . esc_html__( 'Automatic Dataset Updates', 'vn-address-pro' ) . '</label> <select id="vnap-schedule" name="vnap_schedule">';
            foreach ( array( 'disabled' => __( 'Disabled', 'vn-address-pro' ), 'daily' => __( 'Daily', 'vn-address-pro' ), 'weekly' => __( 'Weekly', 'vn-address-pro' ), 'vnap_monthly' => __( 'Monthly (30 days)', 'vn-address-pro' ) ) as $key => $label ) {
                echo '<option value="' . esc_attr( $key ) . '" ' . selected( get_option( DatasetMetadata::SCHEDULE, 'disabled' ), $key, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></p>'; submit_button( __( 'Save Changes', 'vn-address-pro' ) ); echo '</form>';
        } elseif ( $tab === 'data' || $tab === 'status' ) {
            $audit = get_option( DatasetMetadata::AUDIT, array() );
            $badge = __( 'Healthy', 'vn-address-pro' );
            if ( ! ( new LocalAddressRepository() )->has_usable_dataset() ) { $badge = __( 'Dataset Missing', 'vn-address-pro' ); }
            elseif ( is_array( $audit ) && isset( $audit['healthy'] ) && ! $audit['healthy'] ) { $badge = __( 'Integrity Warning', 'vn-address-pro' ); }
            elseif ( ( $s['last_check']['status'] ?? '' ) === 'UPDATE_AVAILABLE' ) { $badge = __( 'Update Available', 'vn-address-pro' ); }
            elseif ( isset( $last['status'] ) && ! in_array( $last['status'], array( 'SUCCESS','NO_UPDATE','HEALTHY' ), true ) ) { $badge = __( 'Error', 'vn-address-pro' ); }
            if ( ( new DatasetSyncLock() )->active() ) { $badge = __( 'Syncing', 'vn-address-pro' ); }
            $rows = $base + array(
                __( 'Status', 'vn-address-pro' ) => $badge,
                __( 'Dataset Schema Version', 'vn-address-pro' ) => $m['schema_version'] ?? '',
                __( 'Source', 'vn-address-pro' ) => $m['source'] ?? '',
                __( 'Generated At (UTC)', 'vn-address-pro' ) => $m['generated_at'] ?? '',
                __( 'Installed At', 'vn-address-pro' ) => $this->date( $m['installed_at'] ?? null ),
                __( 'Province Count', 'vn-address-pro' ) => $m['province_count'] ?? '',
                __( 'Ward Count', 'vn-address-pro' ) => $m['ward_count'] ?? '',
                __( 'Dataset Checksum', 'vn-address-pro' ) => $m['checksum'] ?? '',
                __( 'Last Sync', 'vn-address-pro' ) => $this->date( $last['at'] ?? null ),
                __( 'Last Sync Status', 'vn-address-pro' ) => $last['status'] ?? __( 'Not recorded', 'vn-address-pro' ),
                __( 'Last Successful Sync', 'vn-address-pro' ) => $this->date( $s['last_success']['at'] ?? null ),
                __( 'Last Failure', 'vn-address-pro' ) => $s['last_failure']['status'] ?? '',
                __( 'Remote Provider Status (last observation)', 'vn-address-pro' ) => $s['last_check']['status'] ?? $last['status'] ?? __( 'Not checked', 'vn-address-pro' ),
            );
            if ( $tab === 'status' ) {
                global $wp_version, $wpdb;
                $rows += \Tunlamweb\VNAddressPro\Integrations\WooCommerce\Compatibility::status();
                $rows += array(
                    __( 'WordPress Version', 'vn-address-pro' ) => $wp_version,
                    __( 'PHP Version', 'vn-address-pro' ) => PHP_VERSION,
                    __( 'Local Repository', 'vn-address-pro' ) => ( new LocalAddressRepository() )->has_usable_dataset() ? __( 'Usable', 'vn-address-pro' ) : __( 'Needs Repair', 'vn-address-pro' ),
                    __( 'Scheduled Sync', 'vn-address-pro' ) => get_option( DatasetMetadata::SCHEDULE, 'disabled' ),
                    __( 'Next Sync', 'vn-address-pro' ) => $this->date( wp_next_scheduled( DatasetScheduler::HOOK ) ?: null ),
                    __( 'WP-Cron', 'vn-address-pro' ) => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Disabled; external runner required', 'vn-address-pro' ) : __( 'Enabled', 'vn-address-pro' ),
                    __( 'Persistent Object Cache', 'vn-address-pro' ) => wp_using_ext_object_cache() ? __( 'Yes', 'vn-address-pro' ) : __( 'No', 'vn-address-pro' ),
                );
                foreach ( array( \Tunlamweb\VNAddressPro\Database\TableNames::provinces(), \Tunlamweb\VNAddressPro\Database\TableNames::wards(), $wpdb->options ) as $table ) {
                    $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
                    $rows[ $table ] = strtoupper( (string) $engine ) === 'INNODB' ? __( 'InnoDB — OK', 'vn-address-pro' ) : __( 'Needs Repair: transactional InnoDB required', 'vn-address-pro' );
                }
            }
            $this->rows( $rows );
            if ( $tab === 'data' ) { $this->form( 'check', __( 'Check for Updates', 'vn-address-pro' ) ); $this->form( 'sync', __( 'Sync Now', 'vn-address-pro' ) ); }
        } else {
            echo '<p>' . esc_html__( 'Repair validates current data first, then uses a valid remote candidate or the bundle. Ordinary repair never downgrades. Reinstall uses only the bundled snapshot.', 'vn-address-pro' ) . '</p>';
            $this->form( 'validate', __( 'Validate Dataset', 'vn-address-pro' ) );
            $this->form( 'repair', __( 'Repair Dataset', 'vn-address-pro' ) );
            $this->form( 'reinstall', __( 'Reinstall Bundled Dataset', 'vn-address-pro' ), is_string( $m['dataset_version'] ?? null ) && strcmp( $m['dataset_version'], BundledDataset::VERSION ) > 0 );
            $this->form( 'clear', __( 'Clear Remote Cache (retain LKG)', 'vn-address-pro' ) );
            $audit = get_option( DatasetMetadata::AUDIT, array() );
            if ( is_array( $audit ) && $audit ) { $this->rows( array( __( 'Last Validation', 'vn-address-pro' ) => $this->date( $audit['at'] ?? null ), __( 'Integrity Issues', 'vn-address-pro' ) => implode( ', ', $audit['issues'] ?? array() ) ) ); }
        }
        echo '</div>';
    }
}
