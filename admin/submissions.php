<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function pmat_render_submissions_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pm_assessments';

    // Handle CSV Export
    if (isset($_POST['export_csv']) && check_admin_referer('pmat_export_csv', 'pmat_export_nonce')) {
        pmat_export_submissions_csv();
    }

    // Get filters
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $recommendation = isset($_GET['recommendation']) ? sanitize_text_field($_GET['recommendation']) : '';
    $email_status = isset($_GET['email_status']) ? sanitize_text_field($_GET['email_status']) : '';

    // Build query
    $query = "SELECT * FROM $table_name WHERE 1=1";
    $params = array();

    if ($date_from) {
        $query .= " AND DATE(date_created) >= %s";
        $params[] = $date_from;
    }
    if ($date_to) {
        $query .= " AND DATE(date_created) <= %s";
        $params[] = $date_to;
    }
    if ($recommendation) {
        $query .= " AND recommendation LIKE %s";
        $params[] = '%' . $wpdb->esc_like($recommendation) . '%';
    }
    if ($email_status !== '') {
        $query .= " AND email_sent = %d";
        $params[] = intval($email_status);
    }

    // Add sorting
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date_created';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
    $query .= " ORDER BY $orderby $order";

    // Prepare and get results
    $query = $params ? $wpdb->prepare($query, $params) : $query;
    $submissions = $wpdb->get_results($query);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Assessment Submissions</h1>
        
        <!-- Export Button -->
        <form method="post" style="display: inline-block; margin-left: 10px;">
            <?php wp_nonce_field('pmat_export_csv', 'pmat_export_nonce'); ?>
            <input type="submit" name="export_csv" class="button button-primary" value="Export to CSV">
        </form>

        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" class="alignleft actions">
                <input type="hidden" name="page" value="pm-assessment-submissions">
                
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">
                
                <select name="recommendation">
                    <option value="">All Recommendations</option>
                    <option value="PMaaS" <?php selected($recommendation, 'PMaaS'); ?>>PMaaS</option>
                    <option value="Hybrid" <?php selected($recommendation, 'Hybrid'); ?>>Hybrid</option>
                    <option value="Internal" <?php selected($recommendation, 'Internal'); ?>>Internal</option>
                </select>
                
                <select name="email_status">
                    <option value="">All Email Status</option>
                    <option value="1" <?php selected($email_status, '1'); ?>>Sent</option>
                    <option value="0" <?php selected($email_status, '0'); ?>>Not Sent</option>
                </select>
                
                <input type="submit" class="button" value="Filter">
                <a href="?page=pm-assessment-submissions" class="button">Reset</a>
            </form>
        </div>

        <?php if (!empty($submissions)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo pmat_get_sortable_column_header('date_created', 'Date'); ?></th>
                        <th><?php echo pmat_get_sortable_column_header('name', 'Name'); ?></th>
                        <th><?php echo pmat_get_sortable_column_header('email', 'Email'); ?></th>
                        <th><?php echo pmat_get_sortable_column_header('score', 'Score'); ?></th>
                        <th>Recommendation</th>
                        <th><?php echo pmat_get_sortable_column_header('email_sent', 'Email Status'); ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission) : 
                        $recommendation_data = json_decode($submission->recommendation, true);
                        $recommendation_text = isset($recommendation_data['title']) ? $recommendation_data['title'] : 'N/A';
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($submission->date_created))); ?></td>
                            <td><?php echo esc_html($submission->name); ?></td>
                            <td><?php echo esc_html($submission->email); ?></td>
                            <td><?php echo esc_html($submission->score); ?>%</td>
                            <td><?php echo esc_html($recommendation_text); ?></td>
                            <td>
                                <?php if ($submission->email_sent) : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;" title="Email sent"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-no-alt" style="color: red;" title="Email not sent"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button view-details" 
                                        data-id="<?php echo esc_attr($submission->id); ?>"
                                        onclick="viewSubmissionDetails(<?php echo esc_js($submission->id); ?>)">
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No submissions found.</p>
        <?php endif; ?>
    </div>

    <!-- Modal for submission details -->
    <div id="submission-details-modal" class="pmat-modal">
        <div class="pmat-modal-content">
            <span class="pmat-close">&times;</span>
            <div id="submission-details-content"></div>
        </div>
    </div>

    <script>
    function viewSubmissionDetails(id) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pmat_get_submission_details',
                nonce: '<?php echo wp_create_nonce('pmat_admin_nonce'); ?>',
                submission_id: id
            },
            success: function(response) {
                if (response.success) {
                    jQuery('#submission-details-content').html(response.data.html);
                    jQuery('#submission-details-modal').show();
                } else {
                    alert('Error loading submission details');
                }
            },
            error: function() {
                alert('Error loading submission details');
            }
        });
    }

    jQuery(document).ready(function($) {
        $('.pmat-close').click(function() {
            $('#submission-details-modal').hide();
        });

        $(window).click(function(event) {
            if ($(event.target).is('#submission-details-modal')) {
                $('#submission-details-modal').hide();
            }
        });
    });
    </script>
    <?php
}

// Helper function for sortable columns
function pmat_get_sortable_column_header($column, $title) {
    $current_orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date_created';
    $current_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
    
    $order = ($current_orderby === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $class = $current_orderby === $column ? "sorted $current_order" : "sortable";
    
    $link = add_query_arg(array(
        'orderby' => $column,
        'order' => $order
    ));
    
    return "<a href='$link' class='$class'><span>$title</span><span class='sorting-indicator'></span></a>";
}

// CSV Export Function
function pmat_export_submissions_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pm_assessments';

    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_created DESC");

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="assessment-submissions-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Headers
    fputcsv($output, array(
        'Date',
        'Name',
        'Email',
        'Score',
        'Recommendation',
        'Email Status',
        'Questions & Answers'
    ));

    foreach ($submissions as $submission) {
        $recommendation_data = json_decode($submission->recommendation, true);
        $selections = json_decode($submission->selections, true);
        
        // Format Q&A for CSV
        $qa_text = '';
        foreach ($selections as $selection) {
            $qa_text .= sprintf(
                "Q: %s\nA: %s\n",
                str_replace(array("\r", "\n"), ' ', $selection['question']),
                str_replace(array("\r", "\n"), ' ', $selection['answer'])
            );
        }

        fputcsv($output, array(
            date('Y-m-d H:i', strtotime($submission->date_created)),
            $submission->name,
            $submission->email,
            $submission->score . '%',
            $recommendation_data['title'] ?? 'N/A',
            $submission->email_sent ? 'Sent' : 'Not Sent',
            $qa_text
        ));
    }

    fclose($output);
    exit;
}

// AJAX handler for submission details
add_action('wp_ajax_pmat_get_submission_details', 'pmat_get_submission_details');
function pmat_get_submission_details() {
    check_ajax_referer('pmat_admin_nonce', 'nonce');
    
    if (!isset($_POST['submission_id'])) {
        wp_send_json_error();
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pm_assessments';
    
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        intval($_POST['submission_id'])
    ));

    if (!$submission) {
        wp_send_json_error();
        return;
    }

    $selections = json_decode($submission->selections, true);
    $recommendation = json_decode($submission->recommendation, true);

    ob_start();
    ?>
    <h2>Submission Details</h2>
    <div class="submission-details">
        <p><strong>Name:</strong> <?php echo esc_html($submission->name); ?></p>
        <p><strong>Email:</strong> <?php echo esc_html($submission->email); ?></p>
        <p><strong>Score:</strong> <?php echo esc_html($submission->score); ?>%</p>
        <p><strong>Date:</strong> <?php echo esc_html(date('Y-m-d H:i', strtotime($submission->date_created))); ?></p>
        <p><strong>Email Status:</strong> <?php echo $submission->email_sent ? 'Sent' : 'Not Sent'; ?></p>
        
        <h3>Recommendation</h3>
        <p><strong><?php echo esc_html($recommendation['title'] ?? ''); ?></strong></p>
        <p><?php echo esc_html($recommendation['text'] ?? ''); ?></p>

        <h3>Responses</h3>
        <table class="wp-list-table widefat fixed">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Answer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($selections as $selection) : ?>
                    <tr>
                        <td><?php echo esc_html($selection['question']); ?></td>
                        <td><?php echo esc_html($selection['answer']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(array('html' => $html));
}
