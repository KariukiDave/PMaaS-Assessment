<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['pmat_save_settings']) && check_admin_referer('pmat_settings_nonce')) {
    update_option('pmat_smtp_host', sanitize_text_field($_POST['pmat_smtp_host']));
    update_option('pmat_smtp_port', sanitize_text_field($_POST['pmat_smtp_port']));
    update_option('pmat_smtp_username', sanitize_text_field($_POST['pmat_smtp_username']));
    // Only update password if it's changed (not empty)
    if (!empty($_POST['pmat_smtp_password'])) {
        update_option('pmat_smtp_password', base64_encode($_POST['pmat_smtp_password']));
    }
    update_option('pmat_from_email', sanitize_email($_POST['pmat_from_email']));
    update_option('pmat_from_name', sanitize_text_field($_POST['pmat_from_name']));
    
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

// Get current settings
$smtp_host = get_option('pmat_smtp_host', '');
$smtp_port = get_option('pmat_smtp_port', '587');
$smtp_username = get_option('pmat_smtp_username', '');
$smtp_password = get_option('pmat_smtp_password', ''); // Will be base64 encoded
$from_email = get_option('pmat_from_email', '');
$from_name = get_option('pmat_from_name', 'Creative Bits');
?>

<div class="wrap pmat-settings">
    <h1>PM Assessment Settings</h1>

    <form method="post" action="">
        <?php wp_nonce_field('pmat_settings_nonce'); ?>
        
        <div class="pmat-settings-section">
            <h2>Email Configuration</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pmat_from_name">From Name</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="pmat_from_name" 
                               id="pmat_from_name" 
                               value="<?php echo esc_attr($from_name); ?>" 
                               class="regular-text">
                        <p class="description">The name that will appear in the From field of assessment emails.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="pmat_from_email">From Email</label>
                    </th>
                    <td>
                        <input type="email" 
                               name="pmat_from_email" 
                               id="pmat_from_email" 
                               value="<?php echo esc_attr($from_email); ?>" 
                               class="regular-text">
                        <p class="description">The email address that will be used to send assessment results.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pmat_reply_to_email">Reply-To Email (Optional)</label>
                    </th>
                    <td>
                        <input type="email" 
                               name="pmat_reply_to_email" 
                               id="pmat_reply_to_email" 
                               value="<?php echo esc_attr(get_option('pmat_reply_to_email', '')); ?>" 
                               class="regular-text">
                        <p class="description">Optional email address for replies. If left empty, replies will go to the From Email address.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="pmat-settings-section">
            <h2>SMTP Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pmat_smtp_host">SMTP Host</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="pmat_smtp_host" 
                               id="pmat_smtp_host" 
                               value="<?php echo esc_attr($smtp_host); ?>" 
                               class="regular-text">
                        <p class="description">The hostname of your SMTP server (e.g., smtp.gmail.com).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pmat_smtp_port">SMTP Port</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="pmat_smtp_port" 
                               id="pmat_smtp_port" 
                               value="<?php echo esc_attr($smtp_port); ?>" 
                               class="regular-text">
                        <p class="description">The port your SMTP server uses (usually 587 for TLS or 465 for SSL).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pmat_smtp_username">SMTP Username</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="pmat_smtp_username" 
                               id="pmat_smtp_username" 
                               value="<?php echo esc_attr($smtp_username); ?>" 
                               class="regular-text">
                        <p class="description">Your SMTP account username.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pmat_smtp_password">SMTP Password</label>
                    </th>
                    <td>
                        <input type="password" 
                               name="pmat_smtp_password" 
                               id="pmat_smtp_password" 
                               class="regular-text">
                        <p class="description">Your SMTP account password. Leave blank to keep existing password.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="pmat-settings-section">
            <h2>Test Email Configuration</h2>
            <p>Send a test email to verify your SMTP settings are working correctly.</p>
            <input type="email" 
                   id="test_email" 
                   placeholder="Enter test email address" 
                   class="regular-text">
            <button type="button" 
                    id="send_test_email" 
                    class="button button-secondary">
                Send Test Email
            </button>
            <span id="test_email_result"></span>
        </div>

        <p class="submit">
            <input type="submit" 
                   name="pmat_save_settings" 
                   class="button button-primary" 
                   value="Save Settings">
        </p>
    </form>
</div>
