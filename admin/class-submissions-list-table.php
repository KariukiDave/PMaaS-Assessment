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
        
        // Add debug message
        error_log('PMAT_Submissions_List_Table constructed');
    }

    public function no_items() {
        echo 'No submissions found.';
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'name'           => 'Name',
            'email'          => 'Email',
            'score'          => 'Score',
            'recommendation' => 'Recommendation',
            'submission_date' => 'Date',
            'actions'        => 'Actions'
        ];
    }

    public function get_sortable_columns() {
        return [
            'name'           => ['name', false],
            'email'          => ['email', false],
            'score'          => ['score', false],
            'submission_date' => ['submission_date', true]
        ];
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        global $wpdb;
        $table_name = $wpdb->prefix . 'pmat_submissions';

        // Build query
        $where_clauses = array();
        $values = array();

        // Handle recommendation filter
        if (!empty($_GET['recommendation'])) {
            $where_clauses[] = "JSON_UNQUOTE(JSON_EXTRACT(recommendation, '$.title')) = %s";
            $values[] = sanitize_text_field($_GET['recommendation']);
        }

        // Handle date range filter
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $where_clauses[] = "DATE(submission_date) BETWEEN %s AND %s";
            $values[] = sanitize_text_field($_GET['start_date']);
            $values[] = sanitize_text_field($_GET['end_date']);
        }

        // Build the query
        $sql = "SELECT * FROM $table_name";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $sql .= " ORDER BY submission_date DESC";

        // Prepare and execute the query
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $this->items = $wpdb->get_results($sql);
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="submissions[]" value="%s" />',
            $item->id
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
            case 'email':
                return esc_html($item->$column_name);
            case 'score':
                return esc_html($item->score) . '%';
            case 'recommendation':
                $rec = json_decode($item->recommendation, true);
                return esc_html($rec['title'] ?? '');
            case 'submission_date':
                return esc_html(date('Y-m-d H:i', strtotime($item->submission_date)));
            case 'actions':
                return sprintf(
                    '<a href="#" class="button button-small view-details" data-id="%s">View Details</a>',
                    $item->id
                );
            default:
                return print_r($item, true);
        }
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete',
            'export' => 'Export'
        ];
    }

    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pmat_submissions';

        if ('delete' === $this->current_action()) {
            $submissions = isset($_POST['submissions']) ? $_POST['submissions'] : array();
            if (!empty($submissions)) {
                $submissions = array_map('intval', $submissions);
                $ids = implode(',', $submissions);
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids)");
            }
        }

        if ('export' === $this->current_action()) {
            $submissions = isset($_POST['submissions']) ? $_POST['submissions'] : array();
            if (!empty($submissions)) {
                // Handle export logic here
                // You can add this functionality later
            }
        }
    }

    // Add this method to handle CSV export
    public function export_to_csv() {
        // Start output buffering
        ob_start();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pmat_submissions';

        // Get submissions
        $items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submission_date DESC");

        // Create CSV content
        $output = fopen('php://temp', 'r+');

        // Add UTF-8 BOM
        fwrite($output, "\xEF\xBB\xBF");

        // Add headers
        fputcsv($output, array(
            'Date',
            'Name',
            'Email',
            'Score',
            'Recommendation',
            'Answers'
        ));

        // Add data rows
        foreach ($items as $item) {
            $recommendation = json_decode($item->recommendation, true);
            $selections = json_decode($item->selections, true);
            
            // Format answers
            $answers = array();
            if (is_array($selections)) {
                foreach ($selections as $selection) {
                    $answers[] = $selection['question'] . ': ' . $selection['answer'];
                }
            }

            fputcsv($output, array(
                wp_date('Y-m-d H:i:s', strtotime($item->submission_date)),
                $item->name,
                $item->email,
                $item->score . '%',
                $recommendation['title'] ?? '',
                implode("\n", $answers)
            ));
        }

        // Get the content
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        // Clear all output buffers
        ob_end_clean();
        
        // Disable caching
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="PM Assessments.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: private');
            header('Content-Length: ' . strlen($csv_content));
        }

        // Output the CSV content
        echo $csv_content;
        exit();
    }
}