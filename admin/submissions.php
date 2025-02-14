<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Create an instance of our list table
require_once plugin_dir_path(__FILE__) . 'class-submissions-list-table.php';
$submissions_table = new PMAT_Submissions_List_Table();
$submissions_table->prepare_items();

function pmat_render_submissions_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pmat_submissions'; // Updated table name

    // Get all submissions, ordered by date
    $submissions = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY date_created DESC"
    );
    
    // Rest of the function remains the same...
}
?>

<div class="wrap pmat-submissions">
    <h1>Assessment Submissions</h1>

    <div class="pmat-filters">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
            
            <!-- Date Range Filter -->
            <div class="date-range">
                <label>Date Range:</label>
                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? esc_attr($_GET['start_date']) : ''; ?>">
                <span>to</span>
                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? esc_attr($_GET['end_date']) : ''; ?>">
            </div>

            <!-- Recommendation Filter -->
            <div class="recommendation-filter">
                <label>Recommendation:</label>
                <select name="recommendation">
                    <option value="">All</option>
                    <option value="pmaas" <?php selected(isset($_GET['recommendation']) ? $_GET['recommendation'] : '', 'pmaas'); ?>>PMaaS</option>
                    <option value="hybrid" <?php selected(isset($_GET['recommendation']) ? $_GET['recommendation'] : '', 'hybrid'); ?>>Hybrid</option>
                    <option value="internal" <?php selected(isset($_GET['recommendation']) ? $_GET['recommendation'] : '', 'internal'); ?>>Internal</option>
                </select>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <input type="search" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" placeholder="Search submissions...">
            </div>

            <?php submit_button('Filter', 'secondary', 'filter_action', false); ?>
            <?php 
            if (isset($_GET['filter_action']) || isset($_GET['s']) || isset($_GET['recommendation'])) {
                echo '<a href="' . admin_url('admin.php?page=pm-assessment-submissions') . '" class="button">Reset Filters</a>';
            }
            ?>
        </form>
    </div>

    <!-- Export Button -->
    <div class="pmat-export">
        <form method="post">
            <?php wp_nonce_field('pmat_export_submissions', 'pmat_export_nonce'); ?>
            <input type="submit" name="export_submissions" class="button" value="Export to CSV">
        </form>
    </div>

    <!-- Submissions Table -->
    <form method="post">
        <?php $submissions_table->display(); ?>
    </form>
</div>
