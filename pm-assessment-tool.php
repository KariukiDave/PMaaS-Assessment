<?php
/**
 * Plugin Name: PM Assessment Tool
 * Description: A tool to assess project management needs and provide recommendations
 * Version: 1.2.6
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
        $table_name = $wpdb->prefix . 'pm_assessments';
        
        $insert_result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'score' => $score,
                'recommendation' => is_array($recommendation) ? json_encode($recommendation) : $recommendation,
                'selections' => json_encode($selections),
                'email_sent' => 1,
                'date_created' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%d', '%s')
        );

        if ($insert_result === false) {
            error_log('PM Assessment - Database Error: ' . $wpdb->last_error);
            // Don't throw exception here as email was sent successfully
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
    // Get plugin directory URL for the logo
    $plugin_url = plugin_dir_url(__FILE__);
    
    $content = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Assessment Results</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">';

    // Add header with logo
    $content .= '<div style="background-color: #080244; padding: 20px; text-align: center;">
                    <img src="' . $plugin_url . 'assets/images/creative-bits-logo.png" alt="Creative Bits Logo" style="max-width: 200px; height: auto;">
                    <h1 style="color: #ffffff; margin-top: 15px;">Your Project Management Assessment Results</h1>
                 </div>';

    // Add personal greeting
    $content .= '<div style="padding: 20px; max-width: 600px; margin: 0 auto;">
                    <p>Dear ' . esc_html($name) . ',</p>
                    <p>Thank you for completing the Project Management Assessment. Here are your results:</p>';

    // Add score section
    $content .= '<div style="background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <h2 style="margin: 0;">Your Score: ' . esc_html($score) . '%</h2>
                 </div>';

    // Add recommendation section
    $content .= '<div style="margin: 20px 0;">
                    <h2>Recommendation</h2>
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
                $content .= '<tr>
                                <td style="padding: 12px; border: 1px solid #ddd;">' . esc_html($selection['question']) . '</td>
                                <td style="padding: 12px; border: 1px solid #ddd;">' . esc_html($selection['answer']) . '</td>
                            </tr>';
            }
        }
        
        $content .= '</tbody></table></div>';
    }

    // Add contact section
    $content .= '<div style="margin-top: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                    <p style="margin-bottom: 0;">Need help managing and automating your projects? Our team of experts is ready to assist you.</p>
                    <p style="margin-top: 10px;">Get in touch with us at <a href="mailto:info@creativebits.us" style="color: #fd611c; text-decoration: none;">info@creativebits.us</a> or visit our website <a href="https://creativebits.us" style="color: #fd611c; text-decoration: none;">creativebits.us</a></p>
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