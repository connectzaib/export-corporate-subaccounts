<?php
/**
 * Plugin Name: Export Corporate Subaccounts & Reports
 * Description: Export MemberPress Corporate Account sub-users to CSV and view detailed dashboard reports.
 * Version: 1.1.0
 * Author: Custom
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
    if ( class_exists( 'MeprAppCtrl' ) && class_exists( 'MPCA_Corporate_Account' ) ) {
        new MPCA_Report();
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

    // Restrict to admins
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized access' );
    }

    // Get corporate account IDs
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
                'Owner Name'         => $ca_owner->full_name(),
                'Owner Email'        => $ca_owner->user_email,
                'Subacc. Email'      => $sub_account->user_email,
                'Subacc. Username'   => $sub_account->user_login,
                'Subacc. First Name' => $sub_account->first_name,
                'Subacc. Last Name'  => $sub_account->last_name,
            );
        }
    }

    $filename = 'all-subaccounts-' . time();
    MeprUtils::render_csv( $csv_results, $filename );
    exit;
});
