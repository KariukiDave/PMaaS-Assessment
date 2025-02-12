<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap pmat-dashboard">
    <h1>PM Assessment Dashboard</h1>
    
    <div class="pmat-stats-grid">
        <!-- Total Assessments Card -->
        <div class="pmat-stat-card">
            <h3>Total Assessments</h3>
            <div class="stat-number" id="total-assessments">
                <?php 
                global $wpdb;
                $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pm_assessments");
                echo $total ? $total : '0';
                ?>
            </div>
        </div>

        <!-- Emails Sent Card -->
        <div class="pmat-stat-card">
            <h3>Emails Sent</h3>
            <div class="stat-number" id="total-emails">
                <?php 
                $emails_sent = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pm_assessments WHERE email_sent = 1");
                echo $emails_sent ? $emails_sent : '0';
                ?>
            </div>
        </div>

        <!-- Average Score Card -->
        <div class="pmat-stat-card">
            <h3>Average Score</h3>
            <div class="stat-number" id="average-score">
                <?php 
                $avg_score = $wpdb->get_var("SELECT AVG(score) FROM {$wpdb->prefix}pm_assessments");
                echo $avg_score ? round($avg_score, 1) . '%' : '0%';
                ?>
            </div>
        </div>
    </div>

    <div class="pmat-charts-grid">
        <!-- Assessments Over Time Chart -->
        <div class="pmat-chart-card">
            <h3>Assessments Over Time</h3>
            <canvas id="assessmentsChart"></canvas>
        </div>

        <!-- Recommendation Distribution Chart -->
        <div class="pmat-chart-card">
            <h3>Recommendation Distribution</h3>
            <canvas id="recommendationsChart"></canvas>
        </div>

        <!-- Emails Sent Chart -->
        <div class="pmat-chart-card">
            <h3>Emails Sent (Last 30 Days)</h3>
            <canvas id="emailsChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch chart data via AJAX
    jQuery.post(ajaxurl, {
        action: 'pmat_get_dashboard_data',
        nonce: pmatAdmin.nonce
    }, function(response) {
        if (response.success) {
            const data = response.data;
            
            // Assessments Over Time Chart
            new Chart(document.getElementById('assessmentsChart'), {
                type: 'line',
                data: {
                    labels: data.dates,
                    datasets: [{
                        label: 'Assessments',
                        data: data.assessments,
                        borderColor: '#fd611c',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Recommendation Distribution Chart
            new Chart(document.getElementById('recommendationsChart'), {
                type: 'doughnut',
                data: {
                    labels: ['PMaaS', 'Hybrid', 'Internal'],
                    datasets: [{
                        data: data.recommendations,
                        backgroundColor: ['#fd611c', '#080244', '#4A90E2']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        }
                    }
                }
            });

            // Emails Sent Chart
            new Chart(document.getElementById('emailsChart'), {
                type: 'bar',
                data: {
                    labels: data.emailDates,
                    datasets: [{
                        label: 'Emails Sent',
                        data: data.emailsSent,
                        backgroundColor: '#080244'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    });
});
</script>
