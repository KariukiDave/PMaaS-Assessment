<div class="pmat-container">
    <form id="pm-assessment-form">
        <div class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar"></div>
            </div>
            <div class="progress-text">Question 1 of 10</div>
        </div>

        <div class="question-container">
            <!-- Project Complexity -->
            <div class="question" data-weight="10">
                <h3>1. How complex are your typical projects?</h3>
                <div class="options">
                    <button type="button" data-value="1">High complexity, many stakeholders</button>
                    <button type="button" data-value="2">Medium complexity, few stakeholders</button>
                    <button type="button" data-value="3">Low complexity, straightforward</button>
                </div>
            </div>

            <!-- Project Volume -->
            <div class="question" data-weight="10">
                <h3>2. How many projects do you manage annually?</h3>
                <div class="options">
                    <button type="button" data-value="1">More than 20</button>
                    <button type="button" data-value="2">10-20</button>
                    <button type="button" data-value="3">Less than 10</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Project Management Maturity -->
            <div class="question" data-weight="15">
                <h3>3. How mature are your project management processes?</h3>
                <div class="options">
                    <button type="button" data-value="3">Well-established processes</button>
                    <button type="button" data-value="2">Basic PM processes</button>
                    <button type="button" data-value="1">No formal processes</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Resource Availability -->
            <div class="question" data-weight="15">
                <h3>How available are your project management resources?</h3>
                <div class="options">
                    <button type="button" data-value="3">Dedicated PM team</button>
                    <button type="button" data-value="2">Limited PM resources</button>
                    <button type="button" data-value="1">No internal resources</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Budget Considerations -->
            <div class="question" data-weight="10">
                <h3>5. How flexible is your project management budget?</h3>
                <div class="options">
                    <button type="button" data-value="3">Flexible</button>
                    <button type="button" data-value="2">Moderate constraints</button>
                    <button type="button" data-value="1">Strict limitations</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Strategic Importance -->
            <div class="question" data-weight="10">
                <h3>6. How important are projects to your organization's success?</h3>
                <div class="options">
                    <button type="button" data-value="3">Critical to success</button>
                    <button type="button" data-value="2">Moderately important</button>
                    <button type="button" data-value="1">Supporting role only</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Organizational Control -->
            <div class="question" data-weight="10">
                <h3>7. How important is maintaining direct control over project management?</h3>
                <div class="options">
                    <button type="button" data-value="3">High need for control</button>
                    <button type="button" data-value="2">Moderate control</button>
                    <button type="button" data-value="1">Open to external management</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Scalability Needs -->
            <div class="question" data-weight="10">
                <h3>8. What are your scalability needs?</h3>
                <div class="options">
                    <button type="button" data-value="1">High scalability needed</button>
                    <button type="button" data-value="2">Moderate needs</button>
                    <button type="button" data-value="3">Stable, predictable</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- Industry Expertise -->
            <div class="question" data-weight="10">
                <h3>9. How important is industry-specific expertise?</h3>
                <div class="options">
                    <button type="button" data-value="3">Highly specialized expertise</button>
                    <button type="button" data-value="2">Some expertise needed</button>
                    <button type="button" data-value="1">General PM knowledge is enough</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>

            <!-- PM Tools Expertise -->
            <div class="question" data-weight="10">
                <h3>10. Do you have in-house expertise in modern project management tools and automation?</h3>
                <div class="options">
                    <button type="button" data-value="3">Strong expertise</button>
                    <button type="button" data-value="2">Basic knowledge</button>
                    <button type="button" data-value="1">Limited or no expertise</button>
                </div>
                <button type="button" class="back-button" style="display: none;">Back</button>
            </div>
        </div>

        <div class="results-container" style="display: none;">
            <h2>Your Assessment Results</h2>
            
            <div class="view-selections-section">
                <button type="button" class="view-selections-button">View Your Selections</button>
                
                <div class="selections-summary" style="display: none;">
                    <h3>Your Responses</h3>
                    <table class="selections-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Your Selection</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="recommendation">
                <div class="pmaas-result" style="display: none;">
                    <svg viewBox="0 0 24 24">
                        <path fill="#fd611c" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                    </svg>
                    <h3>Recommended: Project Management as a Service (PMaaS)</h3>
                    <p>Based on your responses, PMaaS would be the most effective solution for your organization. This approach offers flexibility, immediate access to expertise, and scalable resources without the overhead of maintaining an internal team.</p>
                </div>
                <div class="hybrid-result" style="display: none;">
                    <svg viewBox="0 0 24 24">
                        <path fill="#fd611c" d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                    <h3>Recommended: Hybrid Approach</h3>
                    <p>A balanced combination of internal resources and external PMaaS support would best serve your needs. This approach provides flexibility while maintaining internal control over key project aspects.</p>
                </div>
                <div class="internal-result" style="display: none;">
                    <svg viewBox="0 0 24 24">
                        <path fill="#fd611c" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                    <h3>Recommended: Internal Project Management Team</h3>
                    <p>Your organization shows strong capabilities for managing projects internally. Building or maintaining an in-house project management team would be most beneficial for your needs.</p>
                </div>
            </div>
            
            <div class="email-results">
                <h3>Get Your Results by Email</h3>
                <div class="input-group">
                    <input type="text" id="user-name" placeholder="Enter your name" required>
                    <input type="email" id="user-email" placeholder="Enter your email" required>
                </div>
                <button type="button" id="send-results">Send Results</button>
            </div>
            
            <button type="button" class="retake-assessment">Retake Assessment</button>
        </div>
    </form>
</div>

<script>
function generateEmailContent($selections, $result) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                margin: 0;
                padding: 0;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .logo {
                max-width: 200px;
                height: auto;
                margin-bottom: 30px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .thank-you {
                font-size: 24px;
                color: #2C5282;
                margin-bottom: 20px;
            }
            .selections-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .selections-table th, .selections-table td {
                padding: 12px;
                border: 1px solid #E2E8F0;
                text-align: left;
            }
            .selections-table th {
                background-color: #2C5282;
                color: white;
            }
            .result-section {
                background-color: #EBF8FF;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .contact-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #E2E8F0;
                text-align: center;
            }
            .contact-button {
                display: inline-block;
                padding: 12px 24px;
                background-color: #2C5282;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <div class="thank-you">Thank you for completing the assessment!</div>
            </div>
            
            <p>We appreciate you taking the time to evaluate your project management needs. Here are your responses:</p>
            
            <table class="selections-table">
                <tr>
                    <th>Question</th>
                    <th>Your Selection</th>
                </tr>';
    
    foreach ($selections as $question => $answer) {
        $html .= "
                <tr>
                    <td>{$question}</td>
                    <td>{$answer}</td>
                </tr>";
    }
    
    $html .= '
            </table>
            
            <div class="result-section">
                <h2>Your Recommended Solution</h2>
                <p>' . $result['text'] . '</p>
                <img src="' . $result['icon'] . '" alt="Result Icon" style="max-width: 60px; height: auto;">
            </div>
            
            <div class="contact-section">
                <p>Need help managing and automating your projects?<br>Creative Bits is here to assist you!</p>
                <a href="https://creativebits.us/contact-us/" class="contact-button">Contact Creative Bits</a>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
</script>