<?php
/**
 * Plugin Name: Export Corporate Subaccounts & Reports
 * Description: Export MemberPress Corporate Account sub-users to CSV and view detailed dashboard reports.
 * Version: 1.1.0
 * Author: Zaib Makda
 * Author URI: https://profiles.wordpress.org/connectzaib/
 * Text Domain: export-corporate-subaccounts
 * Domain Path: /languages
 * Requires Plugins: memberpress, memberpress-corporate
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'MPCA_EXPORT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MPCA_EXPORT_URL', plugin_dir_url( __FILE__ ) );

// Load classes
require_once MPCA_EXPORT_PATH . 'includes/class-mpca-report.php';

// Initialize
add_action( 'plugins_loaded', function() {
    $mepr_active = class_exists( 'MeprAppCtrl' );
    $mpca_active = class_exists( 'MPCA_Corporate_Account' );

    if ( $mepr_active && $mpca_active ) {
        new MPCA_Report();
    } else {
        add_action( 'admin_notices', function() use ( $mepr_active, $mpca_active ) {
            $missing = array();
            if ( ! $mepr_active ) { $missing[] = 'MemberPress'; }
            if ( ! $mpca_active ) { $missing[] = 'MemberPress Corporate Accounts'; }
            
            echo '<div class="error"><p>';
            printf( 
                /* translators: %s: list of missing plugins */
                esc_html__( 'Export Corporate Subaccounts & Reports requires %s to be installed and active.', 'export-corporate-subaccounts' ),
                '<strong>' . esc_html( implode( ' ' . __( 'and', 'export-corporate-subaccounts' ) . ' ', $missing ) ) . '</strong>'
            );
            echo '</p></div>';
        });
    }
});

/**
 * Legacy Support: Export all sub-accounts via ?export_subaccounts=1
 */
add_action( 'admin_init', function () {
    global $wpdb;

    // Run only when explicitly requested
    if ( ! isset( $_GET['export_subaccounts'] ) && ! isset( $_GET['export_all'] ) ) {
        return;
    }

    // Verify nonce for security
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'mpca_export_all' ) ) {
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
});
