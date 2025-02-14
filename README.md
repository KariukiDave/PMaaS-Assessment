# PM Assessment Tool

A WordPress plugin that helps organizations assess their project management needs and provides tailored recommendations.

## Description

The Project Management Assessment Tool is a comprehensive assessment solution that evaluates an organization's project management requirements through a series of targeted questions. Based on the responses, it provides personalized recommendations for project management approaches: PMaaS, Hybrid, or Internal team solutions.

## Author
David Kariuki  
[Creative Bits](https://creativebits.us)

## Changelog

### 1.2.9 - 2025
- Fixed submissions data storage and display
- Updated database table name to pmat_submissions
- Fixed submissions page data retrieval
- Maintained email functionality while improving data storage
- Enhanced submission tracking system
- Improved error logging for submissions
- Fixed assessment data recording
- Ensured all submissions are properly stored

### 1.2.8 - 2025
- Fixed dashboard data to show all assessments
- Updated submissions tracking to include non-emailed assessments
- Improved assessment data storage
- Removed logo from email template
- Enhanced dashboard analytics
- Separated email sending from assessment recording
- Improved data retrieval queries
- Added proper status tracking for emails

### 1.2.7 - 2025
- Enhanced email template design
- Added white background for logo (dark mode compatible)
- Added prominent recommendation title section
- Fixed apostrophe encoding in questions
- Updated contact information
- Improved contact section layout
- Enhanced visual hierarchy of recommendations
- Improved email content formatting

### 1.2.6 - 2025
- Enhanced email template design
- Added Creative Bits logo to email header
- Converted assessment responses to responsive table format
- Added contact information section
- Improved mobile responsiveness of email
- Updated email styling with brand colors
- Added clickable contact links
- Optimized email layout and spacing

### 1.2.5 - 2025
- Fixed assessment results email delivery issues
- Improved email content generation
- Enhanced error logging and debugging
- Added detailed data validation
- Improved HTML email template
- Added proper data sanitization
- Enhanced database error handling
- Added comprehensive error reporting

### 1.2.4 - 2025
- Fixed security nonce verification in assessment submissions
- Updated AJAX nonce handling
- Improved form submission security
- Fixed email sending security checks

### 1.2.3 - 2025
- Fixed assessment results email sending functionality
- Added comprehensive error handling for email submissions
- Improved client-side email validation
- Enhanced error messaging and user feedback
- Added detailed server-side error logging
- Fixed AJAX response handling
- Improved database error handling
- Added proper security nonce verification

### 1.2.2 - 2025
- Fixed email sending functionality
- Added detailed SMTP error logging
- Improved error handling and user feedback
- Enhanced SMTP configuration
- Added debug mode for email troubleshooting
- Fixed test email functionality in settings
- Improved error messages display

### 1.2.1 - 2025
- Optimized dashboard chart sizes for better visibility
- Improved chart layout and responsiveness
- Enhanced data visualization with better spacing
- Updated chart legends positioning
- Fixed chart height consistency issues

### 1.2.0 - 2025
- Added automatic updates from GitHub repository
- Added update notifications in WordPress plugins page
- Added detailed changelog in update notifications
- Added compatibility checks for WordPress and PHP versions

### 1.1.0 - 2025
- Added comprehensive admin dashboard with analytics
  - Assessment trends chart
  - Recommendation distribution chart
  - Email tracking chart
  - Key statistics display
- Added submissions management page
  - Filterable and sortable submissions table
  - Bulk actions support
  - CSV export functionality
  - Detailed submission view
- Added settings page with email configuration
  - SMTP server configuration
  - Custom From Name and Email
  - Optional Reply-To email setting
  - Email test functionality
- Improved email template with branded design
- Fixed question visibility issue on page load
- Removed back button from first question
- Added proper spacing between form buttons
- Enhanced mobile responsiveness throughout
- Added proper data sanitization and validation
- Implemented secure password storage for SMTP
- Added comprehensive error handling

### 1.0.0 - 2024
- Initial release
- Basic assessment functionality
- 10 weighted questions
- Three recommendation types
- Basic email functionality
- Mobile-responsive design
- Progress tracking
- Back/forward navigation
- Results display with recommendations
- Email capture form

## Installation

1. Upload the plugin files to the `/wp-content/plugins/pm-assessment-tool` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->PM Assessment screen to configure the plugin
4. Place the shortcode `[pm_assessment]` in your page or post

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Configuration

1. Navigate to PM Assessment -> Settings in your WordPress admin
2. Configure your SMTP settings for email delivery
3. Set up your From Name, From Email, and optional Reply-To email
4. Test your email configuration using the test email feature

## Usage

Insert the shortcode `[pm_assessment]` into any page or post where you want the assessment form to appear.

## Support

For support, please contact [Creative Bits](https://creativebits.us/contact-us/).

## License

This plugin is licensed under the GPL v2 or later.
