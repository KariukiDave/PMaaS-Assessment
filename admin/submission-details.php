<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add body class for submission details
add_filter('admin_body_class', function($classes) {
    return $classes . ' pmat-submission-details';
});

// Get submission ID with validation
$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get submission details
global $wpdb;
$table_name = $wpdb->prefix . 'pmat_submissions';
$submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id));

// Check if submission exists
if (!$submission) {
    wp_die(__('Submission not found', 'pm-assessment-tool'));
}

// Parse JSON data with error checking
$recommendation = json_decode($submission->recommendation, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $recommendation = array('title' => '', 'text' => '');
}

$selections = json_decode($submission->selections, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $selections = array();
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Submission Details</h1>
    <a href="<?php echo admin_url('admin.php?page=pm-assessment-submissions'); ?>" class="page-title-action">Back to List</a>

    <div class="submission-details">
        <!-- Top Cards Container -->
        <div class="cards-container">
            <!-- Basic Information Card -->
            <div class="card">
                <h2>Basic Information</h2>
                <table class="form-table">
                    <tr>
                        <th>Name:</th>
                        <td><?php echo esc_html($submission->name); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo esc_html($submission->email); ?></td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td><?php echo esc_html(wp_date('F j, Y g:i a', strtotime($submission->submission_date))); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Score Card with Donut Chart -->
            <div class="card score-card">
                <h2>Assessment Score</h2>
                <div class="donut-chart-container">
                    <canvas id="scoreChart"></canvas>
                    <div class="score-value"><?php echo esc_html($submission->score); ?>%</div>
                </div>
            </div>

            <!-- Recommendation Card -->
            <div class="card">
                <h2>Recommendation</h2>
                <div class="recommendation-content">
                    <h3><?php echo esc_html($recommendation['title'] ?? ''); ?></h3>
                    <p><?php echo esc_html($recommendation['text'] ?? ''); ?></p>
                </div>
            </div>
        </div>

        <!-- Assessment Answers Card -->
        <div class="card answers-card">
            <h2>Assessment Answers</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Answer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($selections)): ?>
                        <?php foreach ($selections as $selection): ?>
                            <tr>
                                <td><?php echo esc_html($selection['question']); ?></td>
                                <td><?php echo esc_html($selection['answer']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
jQuery(document).ready(function($) {
    // Create donut chart
    const ctx = document.getElementById('scoreChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?php echo $submission->score; ?>, <?php echo 100 - $submission->score; ?>],
                backgroundColor: ['#FD611C', '#f0f0f1'],
                borderWidth: 0,
                cutout: '80%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        }
    });
});
</script>

<style>
/* Make all selectors highly specific to our plugin pages */
body.wp-admin.pmat-submission-details #wpcontent .wrap {
    margin-top: 0;
}

body.wp-admin.pmat-submission-details .submission-details {
    margin: 20px 0 0 0;
}

/* Cards Container */
.submission-details .cards-container {
    display: flex;
    gap: 20px;
	margin-top: 20px;
    margin-bottom: 20px;
}

/* Override WP default card styles */
.submission-details .card {
    position: relative;
    margin-top: 0;
    padding: 20px;
    min-width: 0;
    max-width: none;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    background: #fff;
    border-radius: 6px;
    box-sizing: border-box;
}

/* Top row cards */
.submission-details .cards-container .card {
    flex: 1;
}

/* Answers Card */
.submission-details .answers-card {
    margin-top: 20px;
    width: 100%;
}

/* Ensure the answers table fills the card */
.submission-details .answers-card table {
    width: 100%;
}

/* Score Card */
.submission-details .score-card {
    text-align: center;
}

.submission-details .donut-chart-container {
    position: relative;
    width: 150px;
    margin: 0 auto;
    padding: 20px 0;
}

.submission-details .score-value {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 24px;
    font-weight: bold;
    color: #FD611C;
}

/* Headers */
.submission-details .card h2 {
    margin-top: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
    font-size: 16px;
}

/* Basic Information Table */
.submission-details .form-table {
    margin-top: 0;
}

.submission-details .form-table th {
    width: 80px;
    padding: 12px 10px 12px 0;
    font-weight: normal;
    color: #646970;
}

.submission-details .form-table td {
    padding: 12px 10px;
}

/* Recommendation Content Styles */
.submission-details .recommendation-content {
    padding: 10px 0;
    text-align: center;
}

.submission-details .recommendation-content h3 {
    margin: 0 0 15px 0;
    color: #FD611C;
    font-size: 18px;
    font-weight: 600;
    text-align: center;
}

.submission-details .recommendation-content p {
    margin: 0;
    color: #646970;
    font-size: 14px;
    line-height: 1.5;
    text-align: left;
}

/* Answers Table */
.submission-details .wp-list-table th {
    font-weight: normal;
    color: #646970;
}
</style>