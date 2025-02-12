<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class PMAT_Submissions_List_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'date_created'   => 'Date',
            'name'          => 'Name',
            'email'         => 'Email',
            'score'         => 'Score',
            'recommendation' => 'Recommendation',
            'email_sent'    => 'Email Status',
            'actions'       => 'Actions'
        ];
    }

    public function get_sortable_columns() {
        return [
            'date_created'   => ['date_created', true],
            'name'          => ['name', false],
            'email'         => ['email', false],
            'score'         => ['score', false],
            'recommendation' => ['recommendation', false]
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'date_created':
                return date('Y-m-d H:i', strtotime($item->date_created));
            case 'score':
                return $item->score . '%';
            case 'email_sent':
                return $item->email_sent ? '<span class="status-sent">Sent</span>' : '<span class="status-pending">Pending</span>';
            case 'actions':
                return $this->get_row_actions($item);
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }

    protected function get_row_actions($item) {
        $actions = [
            'view'    => sprintf(
                '<a href="#" class="view-details" data-id="%s">View Details</a>',
                $item->id
            ),
            'resend' => sprintf(
                '<a href="%s" class="resend-email">Resend Email</a>',
                wp_nonce_url(admin_url('admin-post.php?action=pmat_resend_email&id=' . $item->id), 'pmat_resend_email_' . $item->id)
            ),
            'delete' => sprintf(
                '<a href="%s" class="delete-submission" onclick="return confirm(\'Are you sure?\')">Delete</a>',
                wp_nonce_url(admin_url('admin-post.php?action=pmat_delete_submission&id=' . $item->id), 'pmat_delete_submission_' . $item->id)
            )
        ];

        return $this->row_actions($actions);
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pm_assessments';

        // Set up pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Set up sorting
        $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby']) : 'date_created';
        $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'DESC';

        // Build query
        $sql = "SELECT * FROM $table_name";
        
        // Add filters
        $where_clauses = [];
        
        if (!empty($_REQUEST['s'])) {
            $search = esc_sql($_REQUEST['s']);
            $where_clauses[] = "(name LIKE '%$search%' OR email LIKE '%$search%')";
        }
        
        if (!empty($_REQUEST['recommendation'])) {
            $rec = esc_sql($_REQUEST['recommendation']);
            $where_clauses[] = "recommendation = '$rec'";
        }
        
        if (!empty($_REQUEST['start_date']) && !empty($_REQUEST['end_date'])) {
            $start = esc_sql($_REQUEST['start_date']);
            $end = esc_sql($_REQUEST['end_date']);
            $where_clauses[] = "DATE(date_created) BETWEEN '$start' AND '$end'";
        }
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY $orderby $order LIMIT $per_page";
        $sql .= ' OFFSET ' . ($current_page - 1) * $per_page;

        // Get items
        $this->items = $wpdb->get_results($sql);

        // Set up pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}