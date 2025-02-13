<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function pmat_render_submissions_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pm_assessments';

    // Get all submissions, ordered by date
    $submissions = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY date_created DESC"
    );
    ?>
    <div class="wrap">
        <h1>Assessment Submissions</h1>
        
        <?php if (!empty($submissions)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Score</th>
                        <th>Recommendation</th>
                        <th>Email Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission) : 
                        $recommendation = json_decode($submission->recommendation, true);
                        $recommendation_text = isset($recommendation['title']) ? $recommendation['title'] : 'N/A';
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
                                <button class="button" onclick="alert('Details view coming soon')">
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
    <?php
}
