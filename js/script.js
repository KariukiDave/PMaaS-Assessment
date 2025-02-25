jQuery(document).ready(function($) {
    let currentQuestion = 0;
    let scores = [];
    let percentageScore = 0;
    let previousSelections = {};
    let userSelections = [];
    const totalQuestions = $('.question').length;

    // Show only first question initially
    $('.question').hide();
    $('.question').first().show();
    updateProgress();

    // Progress update function
    function updateProgress() {
        const progress = ((currentQuestion + 1) / totalQuestions) * 100;
        $('.progress-bar').css('width', progress + '%');
        $('.progress-text').text(`Question ${currentQuestion + 1} of ${totalQuestions}`);
    }

    // Show/hide back button based on current question
    function updateBackButton() {
        if (currentQuestion > 0) {
            // Don't show back button on first question
            $('.question:visible .back-button').show();
        } else {
            $('.back-button').hide();
        }
    }

    // Handle back button click
    $('.back-button').click(function() {
        if (currentQuestion > 0) {
            const $currentQuestion = $('.question:visible');
            
            // Store current selection before going back
            const currentSelectedValue = $currentQuestion.find('button.selected').data('value');
            if (currentSelectedValue) {
                previousSelections[currentQuestion] = currentSelectedValue;
            }
            
            // Remove selected class from current question
            $currentQuestion.find('button.selected').removeClass('selected');
            
            currentQuestion--;
            
            $currentQuestion.fadeOut(500, function() {
                const $prevQuestion = $currentQuestion.prev('.question');
                $prevQuestion.fadeIn(500);
                
                // Show previous selection in secondary color
                if (previousSelections[currentQuestion] !== undefined) {
                    $prevQuestion.find('button').removeClass('selected previous-selection');
                    $prevQuestion.find(`button[data-value="${previousSelections[currentQuestion]}"]`)
                        .addClass('previous-selection');
                }
                
                updateProgress();
                updateBackButton();
            });
        }
    });

    // Handle option selection
    $('.options button').click(function() {
        const $question = $(this).closest('.question');
        const weight = $question.data('weight');
        const value = $(this).data('value');
        
        // Remove both selected and previous-selection classes
        $question.find('button').removeClass('selected previous-selection');
        $(this).addClass('selected');
        
        scores[currentQuestion] = (value * weight);
        previousSelections[currentQuestion] = value;

        // Store the selection with question text
        userSelections[currentQuestion] = {
            question: $question.find('h3').text().replace(/^\d+\.\s*/, ''), // Remove question number
            answer: $(this).text()
        };
        
        if (currentQuestion < totalQuestions - 1) {
            $question.fadeOut(500, function() {
                $(this).next('.question').fadeIn(500);
                currentQuestion++;
                updateProgress();
                updateBackButton();
            });
        } else {
            calculateAndShowResults();
        }
    });

    function calculateAndShowResults() {
        // Calculate total possible score
        let totalPossibleScore = 0;
        $('.question').each(function() {
            totalPossibleScore += $(this).data('weight') * 3;
        });

        // Calculate actual score
        const actualScore = scores.reduce((a, b) => a + (b || 0), 0);
        
        // Calculate percentage
        percentageScore = Math.round((actualScore / totalPossibleScore) * 100);

        console.log('Actual Score:', actualScore);
        console.log('Total Possible Score:', totalPossibleScore);
        console.log('Percentage Score:', percentageScore);

        // First hide all questions
        $('.question').hide();
        $('.question-container').hide();
        $('.progress-container').hide();

        // Hide all results first
        $('.pmaas-result, .hybrid-result, .internal-result').hide();

        // Show appropriate recommendation
        if (percentageScore < 55) {
            $('.pmaas-result').fadeIn(500);
        } else if (percentageScore <= 75) {
            $('.hybrid-result').fadeIn(500);
        } else {
            $('.internal-result').fadeIn(500);
        }

        // Populate the selections table
        const $tableBody = $('.selections-table tbody');
        $tableBody.empty();
        
        userSelections.forEach((selection, index) => {
            if (selection) {
                $tableBody.append(`
                    <tr>
                        <td>${selection.question}</td>
                        <td>${selection.answer}</td>
                    </tr>
                `);
            }
        });

        // Show results container with fade effect
        $('.results-container').fadeIn(500);

        // Scroll to results
        $('html, body').animate({
            scrollTop: $('.results-container').offset().top - 50
        }, 500);
    }

    // Handle view selections button
    $('.view-selections-button').click(function() {
        const $summary = $('.selections-summary');
        const $button = $(this);
        
        if ($summary.is(':visible')) {
            $summary.slideUp(500);
            $button.text('View Your Selections');
        } else {
            $summary.slideDown(500);
            $button.text('Hide Your Selections');
        }
    });

    // Handle email submission
    $('#send-results').click(function() {
        const name = $('#user-name').val();
        const email = $('#user-email').val();
        
        if (!name || !email) {
            alert('Please enter both name and email address.');
            return;
        }

        if (!isValidEmail(email)) {
            alert('Please enter a valid email address.');
            return;
        }

        const visibleResult = $('.recommendation div:visible');
        const recommendation = {
            title: visibleResult.find('h3').text().replace('Recommended: ', ''),
            text: visibleResult.find('p').text(),
            icon: visibleResult.find('svg').prop('outerHTML')
        };

        const button = $(this);
        button.prop('disabled', true).text('Sending...');

        $.ajax({
            url: pmatAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_assessment_results',
                nonce: pmatAjax.nonce,
                name: name,
                email: email,
                score: percentageScore,
                recommendation: recommendation,
                selections: userSelections
            },
            success: function(response) {
                if (response.success) {
                    alert('Results have been sent to your email!');
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to send results. Please try again.'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Error sending results. Please try again or contact support.');
            },
            complete: function() {
                button.prop('disabled', false).text('Send Results');
            }
        });
    });

    // Email validation function
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    // Retake assessment
    $('.retake-assessment').click(function() {
        scores = [];
        currentQuestion = 0;
        percentageScore = 0;
        previousSelections = {};
        userSelections = [];
        
        $('.options button').removeClass('selected previous-selection');
        
        $('.results-container').fadeOut(300, function() {
            $('.progress-container').show();
            $('.question-container').show();
            $('.question').hide();
            $('.question').first().fadeIn(300);
            updateProgress();
            updateBackButton();
        });
        
        $('#user-name').val('');
        $('#user-email').val('');
        $('#send-results').prop('disabled', false).text('Send Results');
        
        $('.selections-summary').hide();
        $('.view-selections-button').text('View Your Selections');

        $('html, body').animate({
            scrollTop: $('.pmat-container').offset().top - 50
        }, 500);
    });

    // Prevent form submission
    $('#pm-assessment-form').on('submit', function(e) {
        e.preventDefault();
        return false;
    });

    // Initialize back button on page load
    updateBackButton();
});