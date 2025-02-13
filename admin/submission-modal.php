<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Modal for submission details -->
<div id="submission-details-modal" class="pmat-modal">
    <div class="pmat-modal-content">
        <span class="pmat-close">&times;</span>
        <div id="submission-details-content"></div>
    </div>
</div>

<script>
function viewSubmissionDetails(id) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'pmat_get_submission_details',
            nonce: pmatAdmin.nonce,
            submission_id: id
        },
        success: function(response) {
            if (response.success) {
                jQuery('#submission-details-content').html(response.data.html);
                jQuery('#submission-details-modal').show();
            } else {
                alert('Error loading submission details');
            }
        },
        error: function() {
            alert('Error loading submission details');
        }
    });
}

jQuery(document).ready(function($) {
    $('.pmat-close').click(function() {
        $('#submission-details-modal').hide();
    });

    $(window).click(function(event) {
        if ($(event.target).is('#submission-details-modal')) {
            $('#submission-details-modal').hide();
        }
    });
});
</script>