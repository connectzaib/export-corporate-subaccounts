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


