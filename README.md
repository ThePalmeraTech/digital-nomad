# Digital Nomad Subscriptions Plugin

A comprehensive WordPress plugin for managing CV distribution and promotional subscriptions with secure download links, integrated PDF viewer, and professional email automation.

## 📋 Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Admin Interface](#admin-interface)
- [Shortcodes](#shortcodes)
- [Email Templates](#email-templates)
- [Security Features](#security-features)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)

## ✨ Features

### Core Functionality
- **CV Distribution System**: Secure CV sharing with unique download links
- **Integrated PDF Viewer**: Professional in-browser CV viewing experience
- **Subscription Management**: Collect and manage email subscriptions
- **Download Analytics**: Track downloads and user engagement
- **Email Automation**: Professional HTML email templates
- **Terms & Conditions**: Customizable legal terms for CV and promotions
- **File Distribution System**: Share any ZIP (or other file) with the same secure workflow

### Security Features
- **Unique Tokens**: 32-character secure tokens for each download
- **Time-based Expiration**: Links expire after 7 days
- **Download Limits**: Maximum 3 downloads per link
- **Email Validation**: Prevent duplicate subscriptions
- **CSRF Protection**: WordPress nonce verification
- **SQL Injection Prevention**: Prepared statements throughout

### User Experience
- **Responsive Design**: Mobile-first approach
- **Professional UI**: Modern gradient designs and animations
- **AJAX Forms**: Seamless form submissions without page reload
- **Error Handling**: Clear user feedback for all scenarios
- **Accessibility**: Screen reader friendly with proper ARIA labels

## 🚀 Installation

### Automatic Installation
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

### Manual Installation
1. Upload the `palmerita-subscriptions` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create required database tables

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## ⚙️ Configuration

### Initial Setup
1. Navigate to **Digital Nomad Subscriptions** in your WordPress admin
2. Go to **CV Manager** to upload your CV file
3. Configure **Terms & Conditions** for legal compliance
4. Test the system using the preview links

### CV Upload
1. Go to **Digital Nomad Subscriptions → CV Manager**
2. Upload a PDF file (max 10MB)
3. The system will automatically rename it to `Hanaley-Palma-CV.pdf`
4. Test the viewer using the "Test Viewer Page" button

### Email & SMTP Configuration
The plugin now ships with its own **Email Settings** panel:

1. Go to **Subscriptions → Email Settings**.
2. Select a provider (Brevo, SendGrid, Zoho) or choose "Custom".
3. Host, Port and Encryption fields are auto-filled; adjust if needed.
4. Enter username/password (or API key), "From Email" and "From Name".
5. (Optional) Enable **reCAPTCHA v3** and add *Site Key* / *Secret*.
6. Save and click **Send Test Email** to verify delivery.

No additional SMTP plugin is required.

## 📖 Usage

### For Visitors
1. **CV Request**: Click "Get my CV" button on your website
2. **Email Submission**: Enter email address in the modal
3. **Email Receipt**: Receive secure viewing link via email
4. **CV Viewing**: Click link to view CV in integrated browser viewer
5. **Download Option**: Download PDF directly from viewer page

### For Administrators
1. **Dashboard**: Monitor subscription statistics and recent activity
2. **CV Management**: Upload, replace, or delete CV files
3. **Subscription Lists**: View and manage all email subscriptions
4. **Terms Management**: Update legal terms and conditions
5. **Analytics**: Track download patterns and user engagement

## 🎛️ Admin Interface

### Dashboard (`/wp-admin/admin.php?page=palmerita-subscriptions`)
- **Statistics Overview**: Total subscriptions, downloads, recent activity
- **Quick Actions**: Direct links to all management pages
- **Recent Subscriptions**: Latest email submissions with timestamps
- **System Status**: CV upload status and configuration checks

### CV Manager (`/wp-admin/admin.php?page=palmerita-cv-manager`)
- **Upload Interface**: Drag-and-drop CV upload with validation
- **File Management**: Replace or delete existing CV files
- **Preview Tools**: Test viewer and direct PDF preview
- **Usage Instructions**: Step-by-step guide for the CV system

### CV List (`/wp-admin/admin.php?page=palmerita-cv-list`)
- **Subscription Table**: Paginated list of all CV requests
- **Search & Filter**: Find specific subscriptions by email or date
- **Bulk Actions**: Mass delete or export subscriptions
- **Download Tracking**: View download counts and last access times

### Promotions List (`/wp-admin/admin.php?page=palmerita-promo-list`)
- **Promotional Subscriptions**: Manage marketing email list
- **Bulk Operations**: Export for email marketing platforms
- **Subscription Analytics**: Track promotional signup patterns

### File Manager (`/wp-admin/admin.php?page=palmerita-file-manager`)
- **ZIP Upload**: Upload/replace the file you wish to distribute
- **Current File**: Quick link to the active ZIP
- **Modal Text**: Set custom title & description for the download modal

### Terms & Conditions (`/wp-admin/admin.php?page=palmerita-terms`)
- **WYSIWYG Editor**: Rich text editing for legal terms
- **Separate Terms**: Different terms for CV and promotional subscriptions
- **Public URLs**: Direct links for transparency and compliance

### Modal Copy (`/wp-admin/admin.php?page=palmerita-modal-copy`)
- **Titles & Descriptions**: Customise modal headline and explanatory copy for each button (CV, Promotions, File).
- **Button Appearance**: Change default label, emoji/icon and background colour without touching code.
- **Non-destructive**: Leave fields empty to keep the built-in defaults.

## 🏷️ Shortcodes

### `[palmerita_subscription_buttons]`
Displays the three action buttons (CV, Promotions, File). Individual copy, icon and colour are controlled from **Subscriptions → Modal Copy** page.

```php
[palmerita_subscription_buttons]
```

**Attributes:**
- `cv_text`: Custom text for CV button (default: "Get my CV")
- `promo_text`: Custom text for promo button (default: "Get Promotions")
- `style`: Button style - "default", "minimal", "gradient" (default: "default")

### `[palmerita_cv_button]`
Displays only the CV subscription button.

```php
[palmerita_cv_button text="Download Resume" style="gradient"]
```

**Attributes:**
- `text`: Button text (default: "Get my CV")
- `style`: Button style (default: "default")
- `class`: Additional CSS classes

### `[palmerita_promo_button]`
Displays only the promotional subscription button.

```php
[palmerita_promo_button text="Join Newsletter" style="minimal"]
```

**Attributes:**
- `text`: Button text (default: "Get Promotions")
- `style`: Button style (default: "default")
- `class`: Additional CSS classes

### `[palmerita_file_button]`
Displays a single button that lets visitors request the shared ZIP file.

```php
[palmerita_file_button text="Download File" style="gradient"]
```

**Attributes:**
- `text`: Button text (default: "Download File")
- `style`: Button style (default: "gradient")
- `class`: Additional CSS classes

## 📧 Email Templates

### CV Download Email
**Subject**: "Your CV Download Link - Digital Nomad Subscriptions"

**Features**:
- Professional gradient header design
- Clear call-to-action button
- Important information box with download details
- Personal introduction and contact information
- Responsive HTML design

### Email Customization
Modify email templates in `/includes/download-manager.php`:

```php
private static function get_download_email_template($download_url) {
    // Customize HTML template here
}
```

## 🔒 Security Features

### Token Security
- **Generation**: `wp_generate_password(32, false)` for cryptographic security
- **Uniqueness**: Database constraint prevents token collisions
- **Expiration**: Automatic expiration after 7 days
- **Single Use**: Configurable download limits (default: 3)

### Data Protection
- **Email Validation**: `is_email()` WordPress validation
- **SQL Injection**: All queries use `$wpdb->prepare()`
- **XSS Prevention**: `esc_html()`, `esc_url()`, `esc_attr()` throughout
- **CSRF Protection**: WordPress nonces on all forms

### Security Features

| Mechanism                     | Description                                                      |
|-------------------------------|------------------------------------------------------------------|
| Unique Tokens (32-char)       | Secure download links, 7-day validity, 3 downloads max           |
| WordPress Nonces (CSRF)       | Protection on AJAX actions and CSV export                        |
| Prepared SQL                  | Prevents SQL injection                                           |
| Honeypot                      | Hidden field blocks basic bots                                   |
| Rate Limiting                 | Max 5 requests per IP every 10 minutes                           |
| reCAPTCHA v3 (optional)       | Behaviour-based risk scoring (score ≥ threshold)                 |
| PHPMailer SMTP                | Authenticated sending via TLS/SSL                                |

## 🧪 Development & Testing

### Setting Up Development Environment

1. **Install Dependencies**
   ```bash
   cd wp-content/plugins/palmerita-subscriptions
   composer install
   ```

2. **Set Up WordPress Test Environment**
   ```bash
   # Install WordPress test suite
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

3. **Configure Test Environment**
   ```bash
   # Set environment variable for tests
   export WP_TESTS_DIR=/tmp/wordpress-tests-lib
   ```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test class
vendor/bin/phpunit tests/test-palmerita-subscriptions.php

# Run specific test method
vendor/bin/phpunit --filter test_successful_cv_subscription
```

### Code Quality

```bash
# Check coding standards
composer cs

# Fix coding standards automatically
composer cbf

# Install coding standards (first time)
composer install-codestandards
```

### Test Coverage

The plugin includes comprehensive unit tests covering:

- ✅ **Plugin Initialization**: Singleton pattern, constants, hooks registration
- ✅ **Database Operations**: Table creation, data insertion, validation
- ✅ **AJAX Handling**: CV and promotional subscription processing
- ✅ **Input Validation**: Email validation, nonce verification, sanitization
- ✅ **Download Management**: Link generation, token validation, expiration
- ✅ **Email System**: Template generation, sending, error handling
- ✅ **Security Features**: Download limits, token security, duplicate prevention
- ✅ **Shortcode Functionality**: Button rendering, attribute handling
- ✅ **Admin Interface**: Menu creation, page rendering
- ✅ **Error Handling**: Edge cases, invalid inputs, system failures

Coverage reports are generated in `tests/coverage/html/` directory.

### Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap
├── helpers/
│   └── test-helper.php       # Test utility functions
├── test-palmerita-subscriptions.php  # Main plugin tests
└── test-download-manager.php  # Download manager tests
```

### Writing Custom Tests

```php
class MyCustomTest extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Set up test environment
        PalmeritaSubscriptionsTestHelper::cleanup_test_data();
    }
    
    public function test_my_functionality() {
        // Create test data
        $subscription_id = PalmeritaSubscriptionsTestHelper::create_test_subscription();
        
        // Test your functionality
        $result = my_function();
        
        // Assert results
        $this->assertTrue($result);
    }
    
    public function tearDown(): void {
        // Clean up
        PalmeritaSubscriptionsTestHelper::cleanup_test_data();
        parent::tearDown();
    }
}

### Access Control
- **Admin Only**: All admin pages require `manage_options` capability
- **Public URLs**: Secure public access for terms and CV viewer
- **Rate Limiting**: Prevents spam through email validation

## 🗃️ Database Schema

### `wp_palmerita_subscriptions`
```sql
CREATE TABLE wp_palmerita_subscriptions (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    email varchar(100) NOT NULL,
    type varchar(20) NOT NULL DEFAULT 'cv',
    created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email_type (email, type),
    KEY email (email),
    KEY type (type),
    KEY created (created)
);
```

### `wp_palmerita_downloads`
```sql
CREATE TABLE wp_palmerita_downloads (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    subscription_id mediumint(9) NOT NULL,
    email varchar(100) NOT NULL,
    token varchar(64) NOT NULL,
    expires datetime NOT NULL,
    downloads int(11) DEFAULT 0,
    max_downloads int(11) DEFAULT 3,
    created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_download datetime NULL,
    status varchar(20) DEFAULT 'active' NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY token (token),
    KEY subscription_id (subscription_id),
    KEY email (email),
    KEY expires (expires)
);
```

## 🔧 API Reference

### AJAX Endpoints

#### Subscribe Action
**Endpoint**: `wp_ajax_palmerita_subscribe` / `wp_ajax_nopriv_palmerita_subscribe`

**Parameters**:
- `email` (string, required): Valid email address
- `type` (string, required): 'cv' or 'promo'
- `nonce` (string, required): WordPress nonce

**Response**:
```json
{
    "success": true,
    "data": {
        "message": "Success message"
    }
}
```

### Public URLs

#### CV Viewer
**URL Pattern**: `/palmerita-cv-viewer/{token}`
- `{token}`: 32-character unique download token
- **Example**: `/palmerita-cv-viewer/abc123def456...`

#### Terms & Conditions
**URL Patterns**: 
- `/palmerita-terms/cv` - CV subscription terms
- `/palmerita-terms/promo` - Promotional subscription terms

#### Download Page (Legacy)
**URL Pattern**: `/palmerita-download/{token}`
- Redirects to CV viewer for better UX

### PHP Classes

#### `PalmeritaSubscriptions`
Main plugin class handling initialization and admin interface.

**Key Methods**:
- `init()`: Initialize plugin hooks and actions
- `create_admin_menu()`: Set up admin menu structure
- `handle_ajax_subscription()`: Process AJAX form submissions

#### `PalmeritaDownloadManager`
Handles secure download link generation and management.

**Key Methods**:
- `generate_download_link($email, $subscription_id)`: Create secure download link
- `process_download($token)`: Validate and process download requests
- `send_download_email($email, $download_url)`: Send professional email
- `cleanup_expired_downloads()`: Maintenance function for old records

## 🐛 Troubleshooting

### Common Issues

#### "CV file not found" Error
**Cause**: CV file not uploaded or incorrect file path
**Solution**:
1. Go to CV Manager in admin
2. Upload a PDF file
3. Verify file exists in `/assets/cv/Hanaley-Palma-CV.pdf`

#### Email Not Sending
**Cause**: WordPress mail configuration issues
**Solution**:
1. Install WP Mail SMTP plugin
2. Configure with reliable SMTP service (SendGrid recommended)
3. Test email from WordPress admin

#### 404 Error on Viewer Page
**Cause**: Rewrite rules not flushed
**Solution**:
1. Go to Settings → Permalinks
2. Click "Save Changes" (flushes rewrite rules)
3. Test viewer URL again

#### Database Tables Not Created
**Cause**: Plugin activation issues or insufficient permissions
**Solution**:
1. Deactivate and reactivate plugin
2. Check database user permissions
3. Verify WordPress can create tables

### Debug Mode
Enable WordPress debug mode to troubleshoot issues:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for error messages.

### Support
For technical support or feature requests:
- **Email**: hana@palmeratech.net
- **Website**: palmeratech.net
- **Documentation**: Check this README for detailed information

## 📊 Performance Considerations

### Database Optimization
- **Indexes**: Proper indexing on frequently queried columns
- **Cleanup**: Automatic removal of expired download records
- **Pagination**: Admin lists use pagination to handle large datasets

### Caching Compatibility
- **Object Caching**: Compatible with Redis, Memcached
- **Page Caching**: Public pages cache-friendly
- **CDN Ready**: Static assets can be served via CDN

### File Management
- **CV Storage**: Single file storage prevents bloat
- **Automatic Cleanup**: Old download records are automatically removed
- **File Size Limits**: 10MB maximum for CV uploads

## 🔄 Changelog

### Version 1.2.0 (Current)
**Release Date**: May 2024

**New Features**:
- 🔄 CSV export now forces direct download (no in-browser rendering)
- ✉️ Built-in SMTP settings panel with provider auto-fill
- 📧 AJAX "Send Test Email" utility
- 🛡️ Honeypot + IP rate-limit + optional reCAPTCHA v3
- 💄 Compact modal (max-width 440 px) and UI polish
- 🛠️ **File Manager** to upload and share a single ZIP with secure tokens
- 📝 **Modal Copy Customization**: New admin panel "Modal Copy" allows users to edit titles, descriptions, and button appearance (text, icon, color) for CV, Promo, and File modals without touching code.
- 📝 Submit button text in the modal is now fully customizable for each type.
- 🛠️ Shortcodes `[palmerita_cv_button]`, `[palmerita_promo_button]`, and `[palmerita_file_button]` now respect the custom text, icon, and colors set in the admin panel.
- 🛠️ Corrected an issue where special characters (e.g., apostrophes) were being escaped with backslashes in the admin panel.
- 🛠️ Adjusted modal CSS to prevent it from stretching to full-width on larger screens, maintaining the design system's integrity.

**Security Enhancements**:
- Includes all mechanisms listed in *Security Features* above

### Version 1.1.0
**Release Date**: May 2024

**Features**:
- 🔄 CSV export now uses `admin_init` to prevent header conflicts.
- ✉️ Updated admin dashboard with new stats and quick actions.
- 🛠️ Security model for file downloads now uses the same token/expiry logic as the CV.

### Version 1.0.0
**Release Date**: January 2025

**Features**:
- ✅ Complete subscription management system
- ✅ Secure download link generation with unique tokens
- ✅ Integrated PDF viewer for professional CV presentation
- ✅ Professional HTML email templates with responsive design
- ✅ Comprehensive admin interface with statistics dashboard
- ✅ Terms & conditions management with WYSIWYG editor
- ✅ Multiple shortcodes for flexible frontend integration
- ✅ Full English translation and professional messaging
- ✅ Security features including CSRF protection and SQL injection prevention
- ✅ Mobile-responsive design with modern UI/UX
- ✅ Automatic cleanup of expired download links
- ✅ Bulk operations for subscription management

**Technical Specifications**:
- WordPress 5.0+ compatibility
- PHP 7.4+ requirement
- MySQL 5.6+ support
- AJAX-powered forms
- RESTful URL structure
- Comprehensive error handling

**Security Enhancements**:
- 32-character unique tokens
- 7-day expiration policy
- 3-download limit per link
- Email validation and duplicate prevention
- WordPress nonce verification
- Prepared SQL statements

## 📝 License

This plugin is proprietary software developed for Digital Nomad Subscriptions. All rights reserved.

**Copyright © 2025 Hanaley Palma - Digital Nomad Subscriptions**

## 🤝 Credits

**Developer**: Hanaley Mosley  
**Company**: Hanamoss  
**Website**: palmeratech.com 
**Email**: hmosley@palmeratech.com 

**Technologies Used**:
- WordPress Plugin API
- PHP 7.4+
- MySQL Database
- HTML5 & CSS3
- JavaScript (ES6+)
- AJAX for seamless UX
- Responsive Web Design

*This documentation is maintained and updated regularly. For the latest version, please check the plugin repository or contact support.*

Stable tag: 1.2.0
Tested up to: 6.5.3 