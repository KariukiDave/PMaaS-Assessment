// Test Email Functionality
jQuery(document).ready(function($) {
    $('#send_test_email').click(function() {
        const testEmail = $('#test_email').val();
        const resultSpan = $('#test_email_result');
        
        if (!testEmail) {
            alert('Please enter a test email address');
            return;
        }

        $(this).prop('disabled', true).text('Sending...');
        resultSpan.removeClass('success error').text('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pmat_test_email',
                nonce: pmatAdmin.nonce,
                email: testEmail
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.addClass('success').text('Test email sent successfully!');
                } else {
                    resultSpan.addClass('error').text('Failed to send test email: ' + response.data.message);
                }
            },
            error: function() {
                resultSpan.addClass('error').text('Failed to send test email');
            },
            complete: function() {
                $('#send_test_email').prop('disabled', false).text('Send Test Email');
            }
        });
    });
});
