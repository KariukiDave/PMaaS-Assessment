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
                    resultSpan.addClass('error').text('Error: ' + (response.data.message || 'Failed to send test email'));
                }
            },
            error: function(xhr, status, error) {
                resultSpan.addClass('error').text('Error: ' + error);
            },
            complete: function() {
                $('#send_test_email').prop('disabled', false).text('Send Test Email');
            }
        });
    });

    // Only run on dashboard page
    if ($('.pmat-dashboard-wrap').length) {
        // Fetch chart data via AJAX
        $.ajax({
            url: pmatAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'pmat_get_dashboard_data',
                nonce: pmatAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
					
			// Generate the last 10 dates (including today)
            const totalDates = 10;
            const today = new Date();
            let datesWithMissingData = [];

            // Loop to generate the last 10 dates
            for (let i = totalDates - 1; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(today.getDate() - i); // Subtract i days from today
                datesWithMissingData.push(date.toISOString().split('T')[0]); // Format as YYYY-MM-DD
            }

            // Helper function to fill missing data
            function fillMissingData(datesWithMissingData, dataDates, dataValues) {
                let filledData = [];
                for (let i = 0; i < totalDates; i++) {
                    const currentDate = datesWithMissingData[i];
                    const index = dataDates.indexOf(currentDate);

                    // If the current date exists in the data, use its value; otherwise, use 0 (no data)
                    if (index !== -1) {
                        filledData.push(dataValues[index]);
                    } else {
                        filledData.push(0);  // No data for this date
                    }
                }
                return filledData;
            }

            // Fill missing data for assessments
            const assessmentsWithMissing = fillMissingData(datesWithMissingData, data.dates, data.assessments);

            // Fill missing data for emails sent
            const emailsWithMissing = fillMissingData(datesWithMissingData, data.emailDates, data.emailsSent);

            // Log the generated data for verification
            console.log('Dates with Missing Data:', datesWithMissingData);
            console.log('Assessments with Missing:', assessmentsWithMissing);
            console.log('Emails with Missing:', emailsWithMissing);


			// Mapping of long labels to short labels
			const labelMapping = {
				'Internal Project Management Team': 'Internal',
				'Hybrid Approach': 'Hybrid',
				'Project Management as a Service (PMaaS)': 'PMaaS'
				};

			// Replace the long labels with short ones
			const shortLabels = data.recommendationLabels.map(label => labelMapping[label] || label);

			// Assessments Over Time Bar Chart
			new Chart(document.getElementById('assessmentsChart'), {
				type: 'bar',  // Change from 'line' to 'bar'
				data: {
					labels: datesWithMissingData,
					datasets: [{
						label: 'Assessments',
						data: assessmentsWithMissing,
						backgroundColor: '#fd611c',  // Bars' color
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
							beginAtZero: true, // Optional: Ensure bars start at zero
							ticks: {
								stepSize: 1
							}
						},
						x: {
							ticks: {
								autoskip:true,
								maxRotation:60,
								minRotation: 50
							}
							}
					}
				}
			});

			// Recommendation Distribution Chart (with shorter labels)
			new Chart(document.getElementById('recommendationsChart'), {
				type: 'doughnut',
				data: {
					labels: shortLabels,  // Use the shortened labels here
					datasets: [{
						data: data.recommendations,
						backgroundColor: ['#080244', '#4A90E2', '#fd611c'].slice(0, shortLabels.length)
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					aspectRatio: 1,
					plugins: {
						legend: {
							position: 'bottom',
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
					labels: datesWithMissingData,
					datasets: [{
						label: 'Emails Sent',
						data: emailsWithMissing,
						backgroundColor: '#fd611c',
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
						},
						x: {
							ticks: {
								autoSkip: true,
								maxRotation: 60,
								minRotation: 50
							}
						}
					}
				}
			});
		}
	},
	error: function(xhr, status, error) {
		console.error('Error fetching dashboard data:', error);
	}
	});
}
});
