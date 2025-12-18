<?php

class MPCA_Report {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_mpca_export_csv', array( $this, 'handle_csv_export' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        add_action( 'admin_init', array( $this, 'handle_all_export' ) );
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'mpca_summary_widget',
            __( 'Corporate Accounts Summary', 'export-corporate-subaccounts' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Corporate Reports', 'export-corporate-subaccounts' ),
            __( 'Corporate Reports', 'export-corporate-subaccounts' ),
            'manage_options',
            'mpca-reports',
            array( $this, 'render_page' ),
            'dashicons-chart-bar',
            30
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_mpca-reports' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'mpca-admin-css', MPCA_EXPORT_URL . 'assets/css/admin.css', array(), '1.1.0' );
        // Add Inter font from Google Fonts
        wp_enqueue_style( 'mpca-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap', array(), '1.0.0' );
    }

    public function render_page() {
        require_once MPCA_EXPORT_PATH . 'includes/class-mpca-report-table.php';
        
        $table = new MPCA_Report_Table();
        $table->prepare_items();
        ?>
        <div class="wrap mpca-report-wrap">
            <div class="mpca-report-header">
                <h1><?php esc_html_e( 'Corporate Account Reports', 'export-corporate-subaccounts' ); ?></h1>
                <div class="mpca-header-actions">
                    <?php
                    $export_all_url = add_query_arg( array(
                        'export_all' => 1,
                        '_wpnonce'   => wp_create_nonce( 'mpca_export_all' ),
                    ) );
                    ?>
                    <a href="<?php echo esc_url( $export_all_url ); ?>" class="mpca-export-btn">
                        <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export All Sub-accounts', 'export-corporate-subaccounts' ); ?>
                    </a>
                </div>
            </div>

            <div class="mpca-card">
                <form method="get" action="admin.php">
                    <input type="hidden" name="page" value="mpca-reports" />
                    <?php wp_nonce_field( 'mpca_report_filter', 'mpca_nonce' ); ?>
                    <?php 
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '';
                    
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ( ! empty( $orderby ) ) : ?>
                        <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
                    <?php endif; ?>
                    <?php 
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ( ! empty( $order ) ) : ?>
                        <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
                    <?php endif; ?>
                    <?php
                    $table->search_box( __( 'Search Accounts', 'export-corporate-subaccounts' ), 'mpca-search' );
                    $table->display();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_dashboard_widget() {
        global $wpdb;

        $stats = get_transient( 'mpca_report_stats' );
        if ( false === $stats ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats_data = $wpdb->get_row( "SELECT COUNT(ca.id) as total_accounts, SUM(ca.num_sub_accounts) as total_seats 
                                     FROM {$wpdb->prefix}mepr_corporate_accounts ca
                                     INNER JOIN {$wpdb->users} u ON ca.user_id = u.ID" );
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $used_seats_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mpca_corporate_account_id'" );
            
            $stats = array(
                'total_accounts' => $stats_data->total_accounts,
                'total_seats'    => $stats_data->total_seats,
                'used_seats'     => $used_seats_count,
            );
            
            set_transient( 'mpca_report_stats', $stats, HOUR_IN_SECONDS );
        }

        $percent = $stats['total_seats'] > 0 ? ( $stats['used_seats'] / $stats['total_seats'] ) * 100 : 0;
        ?>
        <div class="mpca-dashboard-widget">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <div class="mpca-stat-item">
                    <span style="display: block; font-size: 24px; font-weight: 700;"><?php echo number_format( $stats['total_accounts'] ); ?></span>
                    <span style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Total Accounts', 'export-corporate-subaccounts' ); ?></span>
                </div>
                <div class="mpca-stat-item">
                    <span style="display: block; font-size: 24px; font-weight: 700;"><?php echo number_format( $stats['used_seats'] ); ?> / <?php echo number_format( $stats['total_seats'] ); ?></span>
                    <span style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Seats Used', 'export-corporate-subaccounts' ); ?></span>
                </div>
            </div>
            <div class="mpca-progress-container" style="height: 12px; margin-bottom: 10px;">
                <div class="mpca-progress-bar low" style="width: <?php echo esc_attr( min( 100, $percent ) ); ?>%; background-color: #2563eb;"></div>
            </div>
            <p style="margin: 0; font-size: 13px;">
                <strong><?php echo esc_html( round( $percent, 1 ) ); ?>%</strong> <?php esc_html_e( 'of total corporate capacity is currently utilized.', 'export-corporate-subaccounts' ); ?>
            </p>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mpca-reports' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View Detailed Report', 'export-corporate-subaccounts' ); ?></a>
        </div>
        <?php
    }

    public function handle_csv_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'export-corporate-subaccounts' ) );
        }

        $ca_id = isset( $_GET['ca'] ) ? intval( $_GET['ca'] ) : 0;
        if ( ! $ca_id ) {
            wp_die( esc_html__( 'Invalid Corporate Account ID', 'export-corporate-subaccounts' ) );
        }

        check_admin_referer( 'mpca_export_' . $ca_id );

        $ca = new MPCA_Corporate_Account( $ca_id );
        $owner = $ca->user();
        $sub_accounts = $ca->sub_users();

        $csv_results = array();
        foreach ( $sub_accounts as $sub ) {
            $csv_results[] = array(
                __( 'Owner Name', 'export-corporate-subaccounts' )         => $owner->full_name(),
                __( 'Owner Email', 'export-corporate-subaccounts' )        => $owner->user_email,
                __( 'Subacc. Email', 'export-corporate-subaccounts' )      => $sub->user_email,
                __( 'Subacc. Username', 'export-corporate-subaccounts' )   => $sub->user_login,
                __( 'Subacc. First Name', 'export-corporate-subaccounts' ) => $sub->first_name,
                __( 'Subacc. Last Name', 'export-corporate-subaccounts' )  => $sub->last_name,
            );
        }

        $filename = 'subaccounts-' . $ca_id . '-' . time();
        MeprUtils::render_csv( $csv_results, $filename );
        exit;
    }

    /**
     * Handle exporting all sub-accounts
     */
    public function handle_all_export() {
        global $wpdb;

        // Run only when explicitly requested
        if ( ! isset( $_GET['export_all'] ) ) {
            return;
        }

        // Verify nonce for security
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'mpca_export_all' ) ) {
            return;
        }

        // Restrict to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access', 'export-corporate-subaccounts' ) );
        }

        // Get corporate account IDs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $corp_ids = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}mepr_corporate_accounts"
        );

        $csv_results = array();

        foreach ( $corp_ids as $corp ) {
            $ca = new MPCA_Corporate_Account( $corp->id );
            $ca_owner = $ca->user();
            $sub_accounts = $ca->sub_users();

            foreach ( $sub_accounts as $sub_account ) {
                $csv_results[] = array(
                    __( 'Owner Name', 'export-corporate-subaccounts' )         => $ca_owner->full_name(),
                    __( 'Owner Email', 'export-corporate-subaccounts' )        => $ca_owner->user_email,
                    __( 'Subacc. Email', 'export-corporate-subaccounts' )      => $sub_account->user_email,
                    __( 'Subacc. Username', 'export-corporate-subaccounts' )   => $sub_account->user_login,
                    __( 'Subacc. First Name', 'export-corporate-subaccounts' ) => $sub_account->first_name,
                    __( 'Subacc. Last Name', 'export-corporate-subaccounts' )  => $sub_account->last_name,
                );
            }
        }

        $filename = 'all-subaccounts-' . time();
        MeprUtils::render_csv( $csv_results, $filename );
        exit;
    }
}
