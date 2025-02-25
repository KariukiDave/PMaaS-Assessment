<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add body class for submissions
add_filter('admin_body_class', function($classes) {
    return $classes . ' pmat-submissions-page';
});

// Initialize the submissions list table
require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
require_once plugin_dir_path(__FILE__) . 'class-submissions-list-table.php';

// Create an instance of our list table
$submissions_table = new PMAT_Submissions_List_Table();

// Get unique recommendations for filter
global $wpdb;
$table_name = $wpdb->prefix . 'pmat_submissions';
$recommendations = $wpdb->get_results("
    SELECT DISTINCT JSON_UNQUOTE(
        JSON_EXTRACT(recommendation, '$.title')
    ) as title 
    FROM $table_name 
    WHERE recommendation IS NOT NULL
");

// Handle bulk actions if any
$submissions_table->process_bulk_action();

// Prepare items for display
$submissions_table->prepare_items();

// Get any status messages
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$messages = array(
    1 => __('Item deleted successfully.', 'pm-assessment-tool'),
    2 => __('Items deleted successfully.', 'pm-assessment-tool'),
);
?>

<div class="wrap pmat-submissions-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Submissions', 'pm-assessment-tool'); ?>
    </h1>
    
    <?php
    // Show status message if any
    if ($message && isset($messages[$message])) {
        echo '<div class="updated notice is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
    }
    ?>

    <!-- Filter Bar -->
    <div class="pmat-filters">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page'] ?? 'pm-assessment-submissions'); ?>" />
            
            <div class="date-range">
                <label for="date_from">From:</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                
                <label for="date_to">To:</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
            </div>

            <div class="recommendation-filter">
                <label for="recommendation">Recommendation:</label>
                <select name="recommendation" id="recommendation">
                    <option value="">All Approaches</option>
                    <?php foreach ($recommendations as $rec): ?>
                        <option value="<?php echo esc_attr($rec->title); ?>" 
                                <?php selected(isset($_GET['recommendation']) ? $_GET['recommendation'] : '', $rec->title); ?>>
                            <?php echo esc_html($rec->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="Search submissions...">
            </div>

            <?php 
            submit_button(__('Filter', 'pm-assessment-tool'), 'secondary', 'filter', false); 
            
            // Add Reset Filters button if any filter is active
            if (!empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['recommendation']) || !empty($_GET['s'])) {
                echo '<a href="' . esc_url(admin_url('admin.php?page=' . esc_attr($_REQUEST['page'] ?? 'pm-assessment-submissions'))) . '" class="button">Reset Filters</a>';
            }
            ?>
        </form>
    </div>

    <!-- Export Button -->
    <div class="pmat-export">
        <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <input type="hidden" name="action" value="export_submissions_csv">
            <?php wp_nonce_field('export_submissions_csv'); ?>
            <input type="submit" class="button" value="<?php esc_attr_e('Export to CSV', 'pm-assessment-tool'); ?>">
        </form>
    </div>

    <form id="submissions-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page'] ?? ''); ?>" />
        <?php $submissions_table->display(); ?>
    </form>
</div>

<style>
body.wp-admin.pmat-submissions-page #wpcontent .wrap.pmat-submissions-wrap {
    margin-top: 0;
}

.pmat-filters {
    margin: 1em 0;
    padding: 1em;
    background: #fff;
    border: 1px solid #ccd0d4;
    display: flex;
    align-items: center;
    gap: 1em;
    flex-wrap: wrap;
}

.pmat-filters form {
    display: flex;
    align-items: center;
    gap: 1em;
    flex-wrap: wrap;
    width: 100%;
}

.pmat-filters .date-range,
.pmat-filters .recommendation-filter {
    display: flex;
    align-items: center;
    gap: 0.5em;
}

.pmat-filters select {
    min-width: 200px;
}

.wp-list-table .column-cb {
    width: 5%;
}

.wp-list-table .column-score {
    width: 10%;
}

.wp-list-table .column-actions {
    width: 15%;
}

.tablenav .actions {
    padding: 8px 0;
}

.pmat-export {
    margin: 1em 0;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle individual export button
    $('.export-csv-button').click(function(e) {
        e.preventDefault();
        exportSubmissions([]);
    });

    // Handle bulk actions
    $('#doaction, #doaction2').click(function(e) {
        var selectedAction = $(this).prev('select').val();
        if (selectedAction === 'export') {
            e.preventDefault();
            var selectedItems = $('input[name="submissions[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            if (selectedItems.length === 0) {
                alert('Please select items to export.');
                return;
            }
            
            exportSubmissions(selectedItems);
        }
    });

    function exportSubmissions(submissions) {
        // Create a form
        var form = $('<form>', {
            'method': 'POST',
            'action': ajaxurl,
            'target': '_blank'
        });

        // Add necessary fields
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'export_submissions_csv'
        }));

        form.append($('<input>', {
            'type': 'hidden',
            'name': '_wpnonce',
            'value': '<?php echo wp_create_nonce("export_submissions_csv"); ?>'
        }));

        // Add selected submissions if any
        if (submissions.length > 0) {
            submissions.forEach(function(id) {
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'submissions[]',
                    'value': id
                }));
            });
        }

        // Add form to body and submit
        $('body').append(form);
        form.submit();
        form.remove();
    }

    // Handle view details button
    $('.view-details').click(function(e) {
        e.preventDefault();
        var submissionId = $(this).data('id');
        window.location.href = '<?php echo admin_url('admin.php?page=pm-assessment-submission-details&id='); ?>' + submissionId;
    });
});
</script>
