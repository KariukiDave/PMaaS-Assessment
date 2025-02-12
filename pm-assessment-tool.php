<?php
/**
 * Plugin Name: Project Management Assessment Tool
 * Description: A tool to assess project management needs and provide recommendations
 * Version: 1.1.0
 * Author: David Kariuki
 * Author URI: https://creativebits.us
 * Plugin URI: https://github.com/KariukiDave/PMaaS-Assessment
 * GitHub Plugin URI: KariukiDave/PMaaS-Assessment
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add AJAX handlers
add_action('wp_ajax_save_assessment_results', 'pmat_handle_assessment_results');
add_action('wp_ajax_nopriv_save_assessment_results', 'pmat_handle_assessment_results');
add_action('wp_ajax_pmat_get_dashboard_data', 'pmat_get_dashboard_data');
add_action('wp_ajax_pmat_test_email', 'pmat_handle_test_email');

// Activation Hook
register_activation_hook(__FILE__, 'pmat_activate');

function pmat_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pmat_submissions';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        score int(3) NOT NULL,
        recommendation varchar(50) NOT NULL,
        submission_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue scripts and styles
function pmat_enqueue_scripts() {
    wp_enqueue_style('pmat-styles', plugins_url('css/style.css', __FILE__));
    wp_enqueue_script('pmat-script', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0', true);
    
    // Localize the script with new data
    wp_localize_script('pmat-script', 'pmatAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'pmat_enqueue_scripts');

// Shortcode to display the assessment form
function pmat_assessment_shortcode() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/assessment-form.php';
    return ob_get_clean();
}
add_shortcode('pm_assessment', 'pmat_assessment_shortcode');

// Email configuration and sending
function pmat_send_assessment_email($name, $email, $results) {
    $smtp_host = get_option('pmat_smtp_host');
    $smtp_port = get_option('pmat_smtp_port');
    $smtp_username = get_option('pmat_smtp_username');
    $smtp_password = get_option('pmat_smtp_password');

    add_action('phpmailer_init', function($phpmailer) use ($smtp_host, $smtp_port, $smtp_username, $smtp_password) {
        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = $smtp_port;
        $phpmailer->Username = $smtp_username;
        $phpmailer->Password = $smtp_password;
        $phpmailer->SMTPSecure = 'tls';
    });

    $subject = 'Your Project Management Assessment Results';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $message = "
    <html>
    <head>
        <title>Your PM Assessment Results</title>
    </head>
    <body>
        <h2>Hello $name,</h2>
        <p>Thank you for completing our Project Management Assessment. Here are your results:</p>
        $results
        <p>If you have any questions about these results, please don't hesitate to contact us.</p>
    </body>
    </html>
    ";
    
    wp_mail($email, $subject, $message, $headers);
}

// AJAX handler function
function pmat_handle_assessment_results() {
    // Get and sanitize the submitted data
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $score = intval($_POST['score']);
    $recommendation = $_POST['recommendation'];
    $selections = $_POST['selections'];

    // Format selections for email
    $formatted_selections = array();
    foreach ($selections as $selection) {
        if (isset($selection['question']) && isset($selection['answer'])) {
            $formatted_selections[wp_strip_all_tags($selection['question'])] = 
                wp_strip_all_tags($selection['answer']);
        }
    }

    // Prepare result data for email template
    $result = array(
        'text' => wp_strip_all_tags($recommendation['text']),
        'icon' => $recommendation['icon'] // SVG is allowed
    );

    // Generate email content
    $email_content = pmat_generate_email_content($formatted_selections, $result);

    // Get reply-to email
    $reply_to_email = get_option('pmat_reply_to_email');
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('pmat_from_name') . ' <' . get_option('pmat_from_email') . '>'
    );
    
    // Add Reply-To header if set
    if (!empty($reply_to_email)) {
        $headers[] = 'Reply-To: ' . $reply_to_email;
    }

    // Email subject
    $subject = "Your Project Management Assessment Results";

    // Send email
    $sent = wp_mail($email, $subject, $email_content, $headers);

    // Send response
    if ($sent) {
        wp_send_json_success(array('message' => 'Email sent successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to send email'));
    }

    wp_die();
}

// Admin Menu
add_action('admin_menu', 'pmat_add_admin_menu');

function pmat_add_admin_menu() {
    // Main menu item
    add_menu_page(
        'PM Assessment Tool', // Page title
        'PM Assessment', // Menu title
        'manage_options', // Capability
        'pm-assessment', // Menu slug
        'pmat_dashboard_page', // Function to display the page
        'dashicons-chart-bar', // Icon
        30 // Position
    );

    // Submissions submenu
    add_submenu_page(
        'pm-assessment', // Parent slug
        'Assessment Submissions', // Page title
        'Submissions', // Menu title
        'manage_options', // Capability
        'pm-assessment-submissions', // Menu slug
        'pmat_submissions_page' // Function to display the page
    );

    // Settings submenu
    add_submenu_page(
        'pm-assessment', // Parent slug
        'Assessment Settings', // Page title
        'Settings', // Menu title
        'manage_options', // Capability
        'pm-assessment-settings', // Menu slug
        'pmat_settings_page' // Function to display the page
    );
}

// Register settings
add_action('admin_init', 'pmat_register_settings');

function pmat_register_settings() {
    register_setting('pmat_settings_group', 'pmat_smtp_host');
    register_setting('pmat_settings_group', 'pmat_smtp_port');
    register_setting('pmat_settings_group', 'pmat_smtp_username');
    register_setting('pmat_settings_group', 'pmat_smtp_password');
    register_setting('pmat_settings_group', 'pmat_from_email');
    register_setting('pmat_settings_group', 'pmat_from_name');
    register_setting('pmat_settings_group', 'pmat_reply_to_email');
}

// Dashboard page
function pmat_dashboard_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
}

// Submissions page
function pmat_submissions_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/submissions.php';
}

// Settings page
function pmat_settings_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'pmat_admin_enqueue_scripts');

function pmat_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'pm-assessment') === false) {
        return;
    }

    // Enqueue Chart.js
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
    
    // Enqueue our admin scripts and styles
    wp_enqueue_style('pmat-admin-styles', plugins_url('admin/css/admin-style.css', __FILE__));
    wp_enqueue_script('pmat-admin-script', plugins_url('admin/js/admin-script.js', __FILE__), array('jquery', 'chartjs'), '1.0', true);
    
    // Localize script for AJAX
    wp_localize_script('pmat-admin-script', 'pmatAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pmat_admin_nonce')
    ));
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'pmat_deactivate');

function pmat_deactivate() {
    // Any cleanup tasks if needed
}

// Add AJAX handler for dashboard data
function pmat_get_dashboard_data() {
    check_ajax_referer('pmat_admin_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'pm_assessments';

    // Get data for the last 30 days
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    
    // Get daily assessments
    $daily_assessments = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(date_created) as date, COUNT(*) as count 
         FROM $table_name 
         WHERE date_created >= %s 
         GROUP BY DATE(date_created) 
         ORDER BY date",
        $thirty_days_ago
    ));

    // Get recommendation distribution
    $recommendations = $wpdb->get_results(
        "SELECT recommendation, COUNT(*) as count 
         FROM $table_name 
         GROUP BY recommendation"
    );

    // Get daily emails sent
    $daily_emails = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(date_created) as date, COUNT(*) as count 
         FROM $table_name 
         WHERE email_sent = 1 
         AND date_created >= %s 
         GROUP BY DATE(date_created) 
         ORDER BY date",
        $thirty_days_ago
    ));

    // Format data for charts
    $dates = array();
    $assessments = array();
    $emailDates = array();
    $emailsSent = array();
    $recommendationCounts = array(0, 0, 0); // PMaaS, Hybrid, Internal

    // Process daily assessments
    foreach ($daily_assessments as $day) {
        $dates[] = date('M j', strtotime($day->date));
        $assessments[] = (int)$day->count;
    }

    // Process recommendation distribution
    foreach ($recommendations as $rec) {
        if (strpos($rec->recommendation, 'PMaaS') !== false) {
            $recommendationCounts[0] = (int)$rec->count;
        } elseif (strpos($rec->recommendation, 'Hybrid') !== false) {
            $recommendationCounts[1] = (int)$rec->count;
        } else {
            $recommendationCounts[2] = (int)$rec->count;
        }
    }

    // Process daily emails
    foreach ($daily_emails as $day) {
        $emailDates[] = date('M j', strtotime($day->date));
        $emailsSent[] = (int)$day->count;
    }

    wp_send_json_success(array(
        'dates' => $dates,
        'assessments' => $assessments,
        'recommendations' => $recommendationCounts,
        'emailDates' => $emailDates,
        'emailsSent' => $emailsSent
    ));
}

// Also update the test email handler
function pmat_handle_test_email() {
    // ... existing code ...
    
    $reply_to_email = get_option('pmat_reply_to_email');
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('pmat_from_name') . ' <' . get_option('pmat_from_email') . '>'
    );
    
    if (!empty($reply_to_email)) {
        $headers[] = 'Reply-To: ' . $reply_to_email;
    }
    
    // ... rest of the function ...
}

// Initialize the updater
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-updater.php';
if (is_admin()) {
    new PMAT_Plugin_Updater(__FILE__);
}