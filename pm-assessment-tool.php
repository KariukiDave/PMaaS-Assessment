<?php
/**
 * Plugin Name: PM Assessment Tool
 * Description: A tool to assess project management needs and provide recommendations
 * Version: 1.2.3
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

// Update the assessment results handler with better error handling
function pmat_handle_assessment_results() {
    try {
        // Verify AJAX request
        if (!wp_verify_nonce($_REQUEST['nonce'], 'pmat_admin_nonce')) {
            throw new Exception('Security check failed');
        }

        // Get and sanitize the submitted data
        if (!isset($_POST['name']) || !isset($_POST['email'])) {
            throw new Exception('Required fields are missing');
        }

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
        $recommendation = isset($_POST['recommendation']) ? $_POST['recommendation'] : array();
        $selections = isset($_POST['selections']) ? $_POST['selections'] : array();

        if (empty($email) || !is_email($email)) {
            throw new Exception('Invalid email address');
        }

        // Configure SMTP before sending
        add_action('phpmailer_init', 'pmat_configure_smtp');

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

        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('pmat_from_name') . ' <' . get_option('pmat_from_email') . '>'
        );

        // Add Reply-To if set
        $reply_to_email = get_option('pmat_reply_to_email');
        if (!empty($reply_to_email)) {
            $headers[] = 'Reply-To: ' . $reply_to_email;
        }

        // Send email
        $sent = wp_mail($email, "Your Project Management Assessment Results", $email_content, $headers);

        if (!$sent) {
            global $phpmailer;
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                throw new Exception('Email sending failed: ' . $phpmailer->ErrorInfo);
            } else {
                throw new Exception('Email sending failed');
            }
        }

        // Save to database if email was sent successfully
        global $wpdb;
        $table_name = $wpdb->prefix . 'pm_assessments';
        
        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'score' => $score,
                'recommendation' => json_encode($recommendation),
                'selections' => json_encode($selections),
                'email_sent' => 1,
                'date_created' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%d', '%s')
        );

        if ($wpdb->last_error) {
            error_log('Database error: ' . $wpdb->last_error);
            // Continue even if DB save fails, as email was sent
        }

        wp_send_json_success(array('message' => 'Results sent successfully'));

    } catch (Exception $e) {
        error_log('PM Assessment Tool - Email Error: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
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

// Update the dashboard data handler
function pmat_get_dashboard_data() {
    check_ajax_referer('pmat_admin_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'pm_assessments';

    // Get data for the last 30 days
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    
    // Get daily assessments (all assessments, not just emailed ones)
    $daily_assessments = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(date_created) as date, COUNT(*) as count 
         FROM $table_name 
         WHERE date_created >= %s 
         GROUP BY DATE(date_created) 
         ORDER BY date",
        $thirty_days_ago
    ));

    // Get recommendation distribution (all assessments)
    $recommendations = $wpdb->get_results(
        "SELECT 
            CASE 
                WHEN score >= 80 THEN 'Internal'
                WHEN score >= 50 THEN 'Hybrid'
                ELSE 'PMaaS'
            END as recommendation,
            COUNT(*) as count 
         FROM $table_name 
         GROUP BY 
            CASE 
                WHEN score >= 80 THEN 'Internal'
                WHEN score >= 50 THEN 'Hybrid'
                ELSE 'PMaaS'
            END"
    );

    // Get daily emails (only where email_sent = 1)
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

    // Fill in missing dates with zero counts for assessments
    $date = new DateTime($thirty_days_ago);
    $end_date = new DateTime();
    $daily_data = array();

    while ($date <= $end_date) {
        $current_date = $date->format('Y-m-d');
        $dates[] = $date->format('M j');
        $daily_data[$current_date] = 0;
        $date->modify('+1 day');
    }

    // Process daily assessments
    foreach ($daily_assessments as $day) {
        $daily_data[$day->date] = (int)$day->count;
    }
    $assessments = array_values($daily_data);

    // Process recommendation distribution
    foreach ($recommendations as $rec) {
        switch ($rec->recommendation) {
            case 'PMaaS':
                $recommendationCounts[0] = (int)$rec->count;
                break;
            case 'Hybrid':
                $recommendationCounts[1] = (int)$rec->count;
                break;
            case 'Internal':
                $recommendationCounts[2] = (int)$rec->count;
                break;
        }
    }

    // Fill in missing dates with zero counts for emails
    $email_data = array();
    $date->setTimestamp(strtotime($thirty_days_ago));
    while ($date <= $end_date) {
        $current_date = $date->format('Y-m-d');
        $emailDates[] = $date->format('M j');
        $email_data[$current_date] = 0;
        $date->modify('+1 day');
    }

    // Process daily emails
    foreach ($daily_emails as $day) {
        $email_data[$day->date] = (int)$day->count;
    }
    $emailsSent = array_values($email_data);

    wp_send_json_success(array(
        'dates' => $dates,
        'assessments' => $assessments,
        'recommendations' => $recommendationCounts,
        'emailDates' => $emailDates,
        'emailsSent' => $emailsSent
    ));
}

// Update the test email handler with better error handling
function pmat_handle_test_email() {
    check_ajax_referer('pmat_admin_nonce', 'nonce');
    
    $test_email = sanitize_email($_POST['email']);
    
    // Configure SMTP
    add_action('phpmailer_init', 'pmat_configure_smtp');
    
    // Send test email
    $subject = 'PM Assessment Tool - Test Email';
    $message = 'This is a test email from your PM Assessment Tool plugin. If you receive this, your email settings are working correctly.';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('pmat_from_name') . ' <' . get_option('pmat_from_email') . '>'
    );
    
    // Add Reply-To if set
    $reply_to_email = get_option('pmat_reply_to_email');
    if (!empty($reply_to_email)) {
        $headers[] = 'Reply-To: ' . $reply_to_email;
    }

    // Enable debug mode
    global $phpmailer;
    if (!isset($phpmailer)) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }

    try {
        $sent = wp_mail($test_email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success(array('message' => 'Email sent successfully'));
        } else {
            global $phpmailer;
            wp_send_json_error(array('message' => 'Failed to send email: ' . $phpmailer->ErrorInfo));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Exception occurred: ' . $e->getMessage()));
    }
}

// Add SMTP configuration function
function pmat_configure_smtp($phpmailer) {
    $smtp_host = get_option('pmat_smtp_host');
    $smtp_port = get_option('pmat_smtp_port');
    $smtp_username = get_option('pmat_smtp_username');
    $smtp_password = base64_decode(get_option('pmat_smtp_password'));
    
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $smtp_host;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = $smtp_port;
    $phpmailer->Username = $smtp_username;
    $phpmailer->Password = $smtp_password;
    $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $phpmailer->From = get_option('pmat_from_email');
    $phpmailer->FromName = get_option('pmat_from_name');
    
    // Enable debug mode for SMTP
    $phpmailer->SMTPDebug = 2;
    $phpmailer->Debugoutput = 'error_log';
}

// Initialize the updater
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-updater.php';
if (is_admin()) {
    new PMAT_Plugin_Updater(__FILE__);
}