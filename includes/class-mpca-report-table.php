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
            'company'    => 'Account / Company',
            'owner'      => 'Owner',
            'membership' => 'Membership',
            'seats'      => 'Seats Usage',
            'status'     => 'Status',
            'actions'    => 'Actions',
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
            $item['id']
        );
    }

    public function column_owner( $item ) {
        return sprintf(
            '<strong>%1$s</strong><br><span class="mpca-owner-info">%2$s</span>',
            esc_html( $item['owner_name'] ),
            esc_html( $item['owner_email'] )
        );
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
            '<strong>%1$d / %2$d</strong> seats used',
            $used,
            $total
        );

        $html .= '<div class="mpca-progress-container">';
        $html .= sprintf(
            '<div class="mpca-progress-bar %1$s" style="width: %2$d%%;"></div>',
            $class,
            min( 100, $percent )
        );
        $html .= '</div>';
        $html .= sprintf( '<span class="mpca-usage-text">%1$d%% utilized</span>', $percent );

        return $html;
    }

    public function column_status( $item ) {
        $class = $item['status'] === 'enabled' ? 'active' : 'inactive';
        return sprintf(
            '<span class="mpca-badge mpca-badge-%1$s">%2$s</span>',
            $class,
            esc_html( ucfirst( $item['status'] ) )
        );
    }

    public function column_actions( $item ) {
        $export_url = add_query_arg( array(
            'action' => 'mpca_export_csv',
            'ca'     => $item['id'],
            '_wpnonce' => wp_create_nonce( 'mpca_export_' . $item['id'] )
        ), admin_url( 'admin-ajax.php' ) );

        return sprintf(
            '<a href="%1$s" class="button button-small" target="_blank" style="margin-bottom: 4px; display: block; text-align: center;">Manage Sub-accounts</a>' .
            '<a href="%2$s" class="button button-small" style="display: block; text-align: center;">Export CSV</a>',
            esc_url( $item['manage_url'] ),
            esc_url( $export_url )
        );
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
        $sql = "SELECT ca.* FROM {$wpdb->prefix}mepr_corporate_accounts ca 
                LEFT JOIN {$wpdb->users} u ON ca.user_id = u.ID
                LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'mepr_company_name-1'
                $where";

        // Ordering
        $orderby = ! empty( $_REQUEST['orderby'] ) ? esc_sql( $_REQUEST['orderby'] ) : 'id';
        $order = ! empty( $_REQUEST['order'] ) ? esc_sql( $_REQUEST['order'] ) : 'DESC';
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
            $product = $obj ? $obj->product() : null;
            
            $company = get_user_meta( $row->user_id, 'mepr_company_name-1', true );
            if ( empty( $company ) ) {
                $company = get_user_meta( $row->user_id, 'billing_company', true );
            }

            $data[] = array(
                'id'          => $row->id,
                'company'     => $company,
                'owner_name'  => $owner->full_name(),
                'owner_email' => $owner->user_email,
                'membership'  => $product ? $product->post_title : 'N/A',
                'seats_used'  => $ca->num_sub_accounts_used(),
                'seats_total' => $row->num_sub_accounts,
                'status'      => $row->status,
                'manage_url'  => $ca->sub_account_management_url(),
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
