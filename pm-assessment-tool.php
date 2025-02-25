<?php
/**
 * Plugin Name: PM Assessment Tool
 * Description: A tool to assess project management needs and provide recommendations
 * Version: 2.0
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
add_action('wp_ajax_export_submissions_csv', 'pmat_handle_export_csv');

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
        recommendation text NOT NULL,
        selections text,
        email_sent tinyint(1) DEFAULT 0,
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
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pmat_assessment_nonce')
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

// Update the assessment results handler with improved debugging
function pmat_handle_assessment_results() {
    try {
        // Verify AJAX request
        check_ajax_referer('pmat_assessment_nonce', 'nonce');

        // Log incoming data for debugging
        error_log('PM Assessment - Incoming Data: ' . print_r($_POST, true));

        // Get and sanitize the submitted data
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
        
        // Validate required fields
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required');
        }

        if (!is_email($email)) {
            throw new Exception('Invalid email address');
        }

        // Ensure recommendation data exists
        if (!isset($_POST['recommendation']) || empty($_POST['recommendation'])) {
            throw new Exception('Recommendation data is missing');
        }

        $recommendation = $_POST['recommendation'];
        $selections = isset($_POST['selections']) ? $_POST['selections'] : array();

        // Generate email content first to catch any potential issues
        $email_content = pmat_generate_email_content($name, $score, $recommendation, $selections);

        // Configure email settings
        $subject = "Your Project Management Assessment Results";
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('pmat_from_name', 'PM Assessment Tool') . ' <' . get_option('pmat_from_email', '') . '>'
        );

        // Add Reply-To if set
        $reply_to_email = get_option('pmat_reply_to_email');
        if (!empty($reply_to_email)) {
            $headers[] = 'Reply-To: ' . $reply_to_email;
        }

        // Configure SMTP before sending
        add_action('phpmailer_init', 'pmat_configure_smtp');

        // Send email
        $sent = wp_mail($email, $subject, $email_content, $headers);

        if (!$sent) {
            global $phpmailer;
            $error_msg = isset($phpmailer) && isset($phpmailer->ErrorInfo) 
                ? $phpmailer->ErrorInfo 
                : 'Unknown error occurred while sending email';
            error_log('PM Assessment - Email Error: ' . $error_msg);
            throw new Exception($error_msg);
        }

        // Save to database only if email was sent successfully
        global $wpdb;
        $table_name = $wpdb->prefix . 'pmat_submissions';
        
        // Before database insert
        error_log('PM Assessment - Attempting database insert with data: ' . print_r(array(
            'name' => $name,
            'email' => $email,
            'score' => $score,
            'recommendation' => $recommendation,
            'selections' => $selections,
            'email_sent' => 1,
            'submission_date' => current_time('mysql')
        ), true));

        $insert_result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'score' => $score,
                'recommendation' => is_array($recommendation) ? json_encode($recommendation) : $recommendation,
                'selections' => json_encode($selections),
                'email_sent' => 1,
                'submission_date' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%d', '%s')
        );

        if ($insert_result === false) {
            error_log('PM Assessment - Database Error: ' . $wpdb->last_error);
            wp_send_json_error(array(
                'message' => 'Email sent but failed to save submission. Please contact support.',
                'error' => $wpdb->last_error
            ));
            return;
        }

        wp_send_json_success(array(
            'message' => 'Results sent successfully'
        ));

    } catch (Exception $e) {
        error_log('PM Assessment - Error: ' . $e->getMessage());
        error_log('PM Assessment - Stack Trace: ' . $e->getTraceAsString());
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

// Update the email content generation function with improved layout
function pmat_generate_email_content($name, $score, $recommendation, $selections) {
    $content = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Assessment Results</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">';

    // Simple header without logo
    $content .= '<div style="background-color: #080244; padding: 20px; text-align: center;">
                    <h1 style="color: #ffffff; margin: 0;">Your Project Management Assessment Results</h1>
                 </div>';

    // Add personal greeting
    $content .= '<div style="padding: 20px; max-width: 600px; margin: 0 auto;">
                    <p>Dear ' . esc_html($name) . ',</p>
                    <p>Thank you for completing the Project Management Assessment. Here are your results:</p>';

    // Add score section
    $content .= '<div style="background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <h2 style="margin: 0;">Your Score: ' . esc_html($score) . '%</h2>
                 </div>';

    // Add recommendation title in big font
    $content .= '<div style="margin: 20px 0; text-align: center;">
                    <h2 style="font-size: 32px; color: #080244; margin-bottom: 10px;">Recommended Approach:</h2>
                    <h3 style="font-size: 28px; color: #fd611c; margin-top: 0;">' . 
                    (isset($recommendation['title']) ? esc_html($recommendation['title']) : '') . 
                    '</h3>
                 </div>';

    // Add recommendation explanation
    $content .= '<div style="margin: 20px 0;">
                    <h2>Detailed Recommendation</h2>
                    <p>' . (isset($recommendation['text']) ? esc_html($recommendation['text']) : '') . '</p>
                 </div>';

    // Add responses section as a responsive table
    if (!empty($selections)) {
        $content .= '<div style="margin: 20px 0;">
                        <h2>Your Responses</h2>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                            <thead>
                                <tr style="background-color: #080244; color: white;">
                                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Question</th>
                                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Your Answer</th>
                                </tr>
                            </thead>
                            <tbody>';
        
        foreach ($selections as $selection) {
            if (isset($selection['question']) && isset($selection['answer'])) {
                // Fix apostrophe encoding in questions
                $question = str_replace("\'", "'", $selection['question']);
                $answer = str_replace("\'", "'", $selection['answer']);
                
                $content .= '<tr>
                                <td style="padding: 12px; border: 1px solid #ddd;">' . esc_html($question) . '</td>
                                <td style="padding: 12px; border: 1px solid #ddd;">' . esc_html($answer) . '</td>
                            </tr>';
            }
        }
        
        $content .= '</tbody></table></div>';
    }

    // Add contact section with updated details
    $content .= '<div style="margin-top: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                    <p style="margin-bottom: 0;">Need help managing and automating your projects? Our team of experts is ready to assist you.</p>
                    <p style="margin-top: 10px;">Get in touch with us:</p>
                    <ul style="list-style: none; padding: 0; margin: 10px 0;">
                        <li style="margin-bottom: 5px;">Email: <a href="mailto:mail@creativebits.us" style="color: #fd611c; text-decoration: none;">mail@creativebits.us</a></li>
                        <li>Visit: <a href="https://creativebits.us/contact-us/" style="color: #fd611c; text-decoration: none;">https://creativebits.us/contact-us/</a></li>
                    </ul>
                 </div>';

    // Add footer
    $content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <p>Best regards,<br>Creative Bits Team</p>
                 </div>';

    $content .= '</div></body></html>';

    return $content;
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

    // Add hidden submission details page
    add_submenu_page(
        null, // No parent - hide from menu
        'Submission Details',
        'Submission Details',
        'manage_options',
        'pm-assessment-submission-details',
        'pmat_display_submission_details'
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

// Update the admin enqueue function
function pmat_enqueue_admin_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'pm-assessment') === false) {
        return;
    }

    // Enqueue Chart.js first
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);

    // Enqueue our admin script
    wp_enqueue_script('pmat-admin-script', plugins_url('admin/js/admin-script.js', __FILE__), array('jquery', 'chart-js'), '1.0', true);

    // Localize the script with new data
    wp_localize_script('pmat-admin-script', 'pmatAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pmat_dashboard_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'pmat_enqueue_admin_scripts');

// Deactivation hook
register_deactivation_hook(__FILE__, 'pmat_deactivate');

function pmat_deactivate() {
    // Any cleanup tasks if needed
}

// Update the dashboard data handler
function pmat_get_dashboard_data() {
    // Verify nonce first
    if (!check_ajax_referer('pmat_dashboard_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pmat_submissions';

    // Get unique recommendations and their counts
    $recommendations_data = $wpdb->get_results("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(recommendation, '$.title')) as title,
            COUNT(*) as count
        FROM {$table_name}
        WHERE recommendation IS NOT NULL
        GROUP BY JSON_UNQUOTE(JSON_EXTRACT(recommendation, '$.title'))
        ORDER BY count DESC
    ");

    $recommendation_labels = array_map(function($item) {
        return $item->title;
    }, $recommendations_data);

    $recommendation_counts = array_map(function($item) {
        return intval($item->count);
    }, $recommendations_data);

    // Get assessment dates data (last 30 days)
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    $assessment_data = $wpdb->get_results($wpdb->prepare("
        SELECT DATE(submission_date) as date, COUNT(*) as count
        FROM {$table_name}
        WHERE submission_date >= %s
        GROUP BY DATE(submission_date)
        ORDER BY date ASC
    ", $thirty_days_ago));

    $dates = array();
    $assessments = array();
    foreach ($assessment_data as $row) {
        $dates[] = $row->date;
        $assessments[] = intval($row->count);
    }

    // Get email data (last 30 days)
    $email_data = $wpdb->get_results($wpdb->prepare("
        SELECT DATE(submission_date) as date, COUNT(*) as count
        FROM {$table_name}
        WHERE submission_date >= %s AND email_sent = 1
        GROUP BY DATE(submission_date)
        ORDER BY date ASC
    ", $thirty_days_ago));

    $email_dates = array();
    $emails_sent = array();
    foreach ($email_data as $row) {
        $email_dates[] = $row->date;
        $emails_sent[] = intval($row->count);
    }

    // Make sure we exit properly with JSON response
    wp_send_json_success(array(
        'dates' => $dates,
        'assessments' => $assessments,
        'recommendationLabels' => $recommendation_labels,
        'recommendations' => $recommendation_counts,
        'emailDates' => $email_dates,
        'emailsSent' => $emails_sent
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

// Update the AJAX handler to accept specific IDs
function pmat_handle_export_csv() {
    check_admin_referer('export_submissions_csv');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pmat_submissions';

    // Check if specific submissions were selected
    $submission_ids = isset($_POST['submissions']) ? array_map('intval', $_POST['submissions']) : array();

    // Build query
    $sql = "SELECT * FROM $table_name";
    if (!empty($submission_ids)) {
        $ids = implode(',', $submission_ids);
        $sql .= " WHERE id IN ($ids)";
    }
    $sql .= " ORDER BY submission_date DESC";

    // Get submissions
    $items = $wpdb->get_results($sql);

    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="PM Assessments.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM
    fputs($output, "\xEF\xBB\xBF");

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

    fclose($output);
    wp_die();
}

// Add the callback function
function pmat_display_submission_details() {
    require_once plugin_dir_path(__FILE__) . 'admin/submission-details.php';
}

// Add this to your main plugin file where you initialize admin pages

function pmat_admin_init() {
    // Remove any error classes that might be added incorrectly
    add_filter('admin_body_class', function($classes) {
        $classes = str_replace('php-error', '', $classes);
        return $classes;
    }, 99); // High priority to run after other filters
}
add_action('admin_init', 'pmat_admin_init');