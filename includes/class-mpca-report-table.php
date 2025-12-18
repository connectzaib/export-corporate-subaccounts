<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MPCA_Report_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'corporate_account',
            'plural'   => 'corporate_accounts',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'company'    => __( 'Account / Company', 'export-corporate-subaccounts' ),
            'owner'      => __( 'Owner', 'export-corporate-subaccounts' ),
            'membership' => __( 'Membership', 'export-corporate-subaccounts' ),
            'seats'      => __( 'Seats Usage', 'export-corporate-subaccounts' ),
            'status'     => __( 'Status', 'export-corporate-subaccounts' ),
            'actions'    => __( 'Actions', 'export-corporate-subaccounts' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'company' => array( 'company', false ),
            'seats'   => array( 'seats_used', false ),
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'company':
            case 'owner':
            case 'membership':
            case 'seats':
            case 'status':
            case 'actions':
                return $item[ $column_name ];
            default:
                return print_r( $item, true );
        }
    }

    public function column_company( $item ) {
        $name = ! empty( $item['company'] ) ? $item['company'] : $item['owner_name'];
        return sprintf(
            '<span class="mpca-company-name">%1$s</span><span class="mpca-owner-info">ID: %2$s</span>',
            esc_html( $name ),
            esc_html( $item['id'] )
        );
    }

    public function column_owner( $item ) {
        $edit_url = admin_url( 'user-edit.php?user_id=' . $item['owner_id'] );
        return sprintf(
            '<strong>%1$s</strong><br><span class="mpca-owner-info">%2$s</span><br>' .
            '<a href="%3$s" target="_blank" class="mpca-edit-link" style="font-size: 11px; text-decoration: none; color: #2563eb;"><span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> %4$s</a>',
            esc_html( $item['owner_name'] ),
            esc_html( $item['owner_email'] ),
            esc_url( $edit_url ),
            __( 'Edit User', 'export-corporate-subaccounts' )
        );
    }

    public function column_membership( $item ) {
        $html = $item['membership'];
        if ( ! empty( $item['membership_id'] ) ) {
            $edit_url = admin_url( 'post.php?post=' . $item['membership_id'] . '&action=edit' );
            $html .= sprintf(
                '<br><a href="%1$s" target="_blank" class="mpca-edit-link" style="font-size: 11px; text-decoration: none; color: #2563eb;"><span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> %2$s</a>',
                esc_url( $edit_url ),
                __( 'Edit Membership', 'export-corporate-subaccounts' )
            );
        }
        return $html;
    }

    public function column_seats( $item ) {
        $used = $item['seats_used'];
        $total = $item['seats_total'];
        $percent = $total > 0 ? ( $used / $total ) * 100 : 0;
        
        $class = 'low';
        if ( $percent > 90 ) {
            $class = 'high';
        } elseif ( $percent > 70 ) {
            $class = 'medium';
        }

        $html = sprintf(
            '<strong>%1$d / %2$d</strong> %3$s',
            $used,
            $total,
            __( 'seats used', 'export-corporate-subaccounts' )
        );

        $html .= '<div class="mpca-progress-container">';
        $html .= sprintf(
            '<div class="mpca-progress-bar %1$s" style="width: %2$d%%;"></div>',
            esc_attr( $class ),
            min( 100, $percent )
        );
        $html .= '</div>';
        $html .= sprintf( '<span class="mpca-usage-text">%1$d%% %2$s</span>', esc_html( $percent ), esc_html__( 'utilized', 'export-corporate-subaccounts' ) );

        return $html;
    }

    public function column_status( $item ) {
        $status = $item['status'];
        $label = ucfirst( $status );
        $class = $status;

        // Use MemberPress human readable labels if possible
        if ( class_exists( 'MeprAppHelper' ) && $item['status_type'] !== 'corporate' ) {
            $label = MeprAppHelper::human_readable_status( $status, $item['status_type'] );
        }

        if ( isset( $item['is_broken'] ) && $item['is_broken'] ) {
            $class = 'broken';
        }

        return sprintf(
            '<span class="mpca-badge mpca-badge-%1$s">%2$s</span>',
            esc_attr( $class ),
            esc_html( $label )
        );
    }

    public function column_actions( $item ) {
        $export_url = add_query_arg( array(
            'action' => 'mpca_export_csv',
            'ca'     => $item['id'],
            '_wpnonce' => wp_create_nonce( 'mpca_export_' . $item['id'] )
        ), admin_url( 'admin-ajax.php' ) );

        $actions = sprintf(
            '<a href="%1$s" class="button button-small" target="_blank" style="margin-bottom: 4px; display: block; text-align: center;">%2$s</a>',
            esc_url( $item['manage_url'] ),
            __( 'Manage Sub-accounts', 'export-corporate-subaccounts' )
        );

        if ( $item['seats_used'] > 0 ) {
            $actions .= sprintf(
                '<a href="%1$s" class="button button-small" style="display: block; text-align: center;">%2$s</a>',
                esc_url( $export_url ),
                __( 'Export CSV', 'export-corporate-subaccounts' )
            );
        }

        return $actions;
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Search
        $where = "";
        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = esc_sql( $_REQUEST['s'] );
            $where = " WHERE (u.display_name LIKE '%$search%' OR u.user_email LIKE '%$search%' OR um.meta_value LIKE '%$search%')";
        }

        // Query
        $sql = "SELECT ca.*, 
                um.meta_value as company_name,
                (SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'mpca_corporate_account_id' AND meta_value = ca.id) as seats_used_count
                FROM {$wpdb->prefix}mepr_corporate_accounts ca 
                INNER JOIN {$wpdb->users} u ON ca.user_id = u.ID
                LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'mepr_company_name-1'
                $where";

        // Ordering
        $orderby_request = ! empty( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'id';
        $order = ! empty( $_REQUEST['order'] ) ? esc_sql( $_REQUEST['order'] ) : 'DESC';
        
        // Map request orderby to SQL columns
        $orderby = 'ca.id';
        if ( $orderby_request === 'company' ) {
            $orderby = 'company_name';
        } elseif ( $orderby_request === 'seats_used' ) {
            $orderby = 'seats_used_count';
        }
        
        $sql .= " ORDER BY $orderby $order";

        // Pagination
        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM ({$sql}) as t" );
        $sql .= " LIMIT $per_page OFFSET $offset";

        $results = $wpdb->get_results( $sql );
        $data = array();

        foreach ( $results as $row ) {
            $ca = new MPCA_Corporate_Account( $row->id );
            $owner = $ca->user();
            $obj = $ca->get_obj();

            $membership_name = 'N/A';
            $membership_id = 0;
            $membership_status = $row->status;
            $status_type = 'corporate';
            $is_broken = false;

            if ( $obj && $obj->id > 0 ) {
                $product = $obj->product();
                $membership_name = $product ? $product->post_title : 'N/A';
                $membership_id = $product ? $product->ID : 0;
                $membership_status = $obj->status;
                $status_type = ( $obj instanceof MeprSubscription ) ? 'subscription' : 'transaction';
            } else {
                $membership_name = '<span style="color: #ef4444; font-weight: 600;"><span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> ' . __( 'Missing Transaction', 'export-corporate-subaccounts' ) . '</span><br><small style="color: #64748b; font-weight: 400;">(' . __( 'Manually added/modified', 'export-corporate-subaccounts' ) . ')</small>';
                $is_broken = true;
            }
            
            $company = get_user_meta( $row->user_id, 'mepr_company_name-1', true );
            if ( empty( $company ) ) {
                $company = get_user_meta( $row->user_id, 'billing_company', true );
            }

            $data[] = array(
                'id'            => $row->id,
                'company'       => $company,
                'owner_id'      => $row->user_id,
                'owner_name'    => $owner->full_name(),
                'owner_email'   => $owner->user_email,
                'membership'    => $membership_name,
                'membership_id' => $membership_id,
                'seats_used'    => $ca->num_sub_accounts_used(),
                'seats_total'   => $row->num_sub_accounts,
                'status'        => $membership_status,
                'status_type'   => $status_type,
                'is_broken'     => $is_broken,
                'manage_url'    => $ca->sub_account_management_url(),
            );
        }

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }
}
