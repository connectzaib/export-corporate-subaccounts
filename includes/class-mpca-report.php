<?php

class MPCA_Report {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_mpca_export_csv', array( $this, 'handle_csv_export' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'mpca_summary_widget',
            'Corporate Accounts Summary',
            array( $this, 'render_dashboard_widget' )
        );
    }

    public function register_menu() {
        add_menu_page(
            'Corporate Reports',
            'Corporate Reports',
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
        wp_enqueue_style( 'mpca-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap', array(), null );
    }

    public function render_page() {
        require_once MPCA_EXPORT_PATH . 'includes/class-mpca-report-table.php';
        
        $table = new MPCA_Report_Table();
        $table->prepare_items();
        ?>
        <div class="wrap mpca-report-wrap">
            <div class="mpca-report-header">
                <h1>Corporate Account Reports</h1>
                <div class="mpca-header-actions">
                    <a href="<?php echo esc_url( add_query_arg( 'export_all', 1 ) ); ?>" class="mpca-export-btn">
                        <span class="dashicons dashicons-download"></span> Export All Sub-accounts
                    </a>
                </div>
            </div>

            <div class="mpca-card">
                <form method="get">
                    <input type="hidden" name="page" value="mpca-reports" />
                    <?php
                    $table->search_box( 'Search Accounts', 'mpca-search' );
                    $table->display();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_dashboard_widget() {
        global $wpdb;
        $stats = $wpdb->get_row( "SELECT COUNT(ca.id) as total_accounts, SUM(ca.num_sub_accounts) as total_seats 
                                 FROM {$wpdb->prefix}mepr_corporate_accounts ca
                                 INNER JOIN {$wpdb->users} u ON ca.user_id = u.ID" );
        
        // This might be slow if there are thousands of accounts, but for 556 it's okay.
        // For better performance, we could cache this or use a direct SQL for used seats.
        $used_seats = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mpca_corporate_account_id'" );

        $percent = $stats->total_seats > 0 ? ( $used_seats / $stats->total_seats ) * 100 : 0;
        ?>
        <div class="mpca-dashboard-widget">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <div class="mpca-stat-item">
                    <span style="display: block; font-size: 24px; font-weight: 700;"><?php echo number_format( $stats->total_accounts ); ?></span>
                    <span style="font-size: 12px; color: #64748b;">Total Accounts</span>
                </div>
                <div class="mpca-stat-item">
                    <span style="display: block; font-size: 24px; font-weight: 700;"><?php echo number_format( $used_seats ); ?> / <?php echo number_format( $stats->total_seats ); ?></span>
                    <span style="font-size: 12px; color: #64748b;">Seats Used</span>
                </div>
            </div>
            <div class="mpca-progress-container" style="height: 12px; margin-bottom: 10px;">
                <div class="mpca-progress-bar low" style="width: <?php echo min( 100, $percent ); ?>%; background-color: #2563eb;"></div>
            </div>
            <p style="margin: 0; font-size: 13px;">
                <strong><?php echo round( $percent, 1 ); ?>%</strong> of total corporate capacity is currently utilized.
            </p>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <a href="<?php echo admin_url( 'admin.php?page=mpca-reports' ); ?>" class="button button-primary">View Detailed Report</a>
        </div>
        <?php
    }

    public function handle_csv_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $ca_id = isset( $_GET['ca'] ) ? intval( $_GET['ca'] ) : 0;
        if ( ! $ca_id ) {
            wp_die( 'Invalid Corporate Account ID' );
        }

        check_admin_referer( 'mpca_export_' . $ca_id );

        $ca = new MPCA_Corporate_Account( $ca_id );
        $owner = $ca->user();
        $sub_accounts = $ca->sub_users();

        $csv_results = array();
        foreach ( $sub_accounts as $sub ) {
            $csv_results[] = array(
                'Owner Name'         => $owner->full_name(),
                'Owner Email'        => $owner->user_email,
                'Subacc. Email'      => $sub->user_email,
                'Subacc. Username'   => $sub->user_login,
                'Subacc. First Name' => $sub->first_name,
                'Subacc. Last Name'  => $sub->last_name,
            );
        }

        $filename = 'subaccounts-' . $ca_id . '-' . time();
        MeprUtils::render_csv( $csv_results, $filename );
        exit;
    }
}
