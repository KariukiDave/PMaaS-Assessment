<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add body class for dashboard
add_filter('admin_body_class', function($classes) {
    return $classes . ' pmat-dashboard-page';
});

// Get stats data
global $wpdb;
$table_name = $wpdb->prefix . 'pmat_submissions';

$total_assessments = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE email_sent = 1");
$average_score = $wpdb->get_var("SELECT AVG(score) FROM $table_name");
?>

<div class="wrap pmat-dashboard-wrap">
    <h1>PM Assessment Dashboard</h1>
    
    <!-- Stats Grid -->
    <div class="pmat-stats-grid">
        <div class="pmat-stat-card">
            <span class="stat-label">Total Assessments</span>
            <span class="stat-value"><?php echo number_format($total_assessments); ?></span>
        </div>
        
        <div class="pmat-stat-card">
            <span class="stat-label">Emails Sent</span>
            <span class="stat-value"><?php echo number_format($total_emails); ?></span>
        </div>
        
        <div class="pmat-stat-card">
            <span class="stat-label">Average Score</span>
            <span class="stat-value"><?php echo number_format($average_score, 1); ?>%</span>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="pmat-charts-grid">
        <div class="pmat-chart-card">
            <h3>Assessments Over Time</h3>
            <canvas id="assessmentsChart"></canvas>
        </div>

        <div class="pmat-chart-card">
            <h3>Recommendation Distribution</h3>
            <canvas id="recommendationsChart"></canvas>
        </div>

        <div class="pmat-chart-card">
            <h3>Emails Sent (Last 30 Days)</h3>
            <canvas id="emailsChart"></canvas>
        </div>
    </div>
</div>

<style>
.pmat-dashboard-wrap {
    padding: 20px;
}

.pmat-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
	margin-top: 20px !important;

}

.pmat-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 14px;
    font-weight: bold;
    color: #080244;
    margin-bottom: 8px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #fd611c;
	}
.pmat-charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.pmat-chart-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.pmat-chart-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #080244;
}

.pmat-chart-card canvas {
    height: 300px !important;
}
#recommendationsChart {
        display: block;  /* Ensures it's a block-level element */
        width: 100%;     /* Width takes up full parent width */
        height: 300px;   /* Set the height (make sure it's the same or related to width for a circle) */
        max-width: 300px; /* Optional: restrict the max width */
        max-height: 300px; /* Optional: restrict the max height */
        margin: auto;    /* Center the chart if needed */
</style>
