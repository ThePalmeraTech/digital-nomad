<?php
/**
 * Download Manager Class
 * Handles unique download links for CV requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class PalmeritaDownloadManager {
    
    /**
     * Generate unique download link for CV request
     */
    public static function generate_download_link($email, $subscription_id) {
        global $wpdb;
        
        // Generate unique token
        $token = wp_generate_password(32, false);
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store download link in database
        $table_name = $wpdb->prefix . 'palmerita_downloads';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'subscription_id' => $subscription_id,
                'email' => $email,
                'token' => $token,
                'expires' => $expires,
                'downloads' => 0,
                'max_downloads' => 3,
                'created' => current_time('mysql'),
                'status' => 'active'
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            return site_url('/palmerita-cv-viewer/' . $token);
        }
        
        return false;
    }
    
    /**
     * Validate and process download request
     */
    public static function process_download($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'palmerita_downloads';
        
        // Get download record
        $download = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'active'",
            $token
        ));
        
        if (!$download) {
            return array('error' => 'Invalid or expired download link.');
        }
        
        // Check expiration
        if (strtotime($download->expires) < current_time('timestamp')) {
            $wpdb->update(
                $table_name,
                array('status' => 'expired'),
                array('id' => $download->id),
                array('%s'),
                array('%d')
            );
            return array('error' => 'This download link has expired. Please request a new one.');
        }
        
        // Check download limit
        if ($download->downloads >= $download->max_downloads) {
            $wpdb->update(
                $table_name,
                array('status' => 'exhausted'),
                array('id' => $download->id),
                array('%s'),
                array('%d')
            );
            return array('error' => 'Download limit reached. Please request a new link.');
        }
        
        // Increment download count
        $wpdb->update(
            $table_name,
            array(
                'downloads' => $download->downloads + 1,
                'last_download' => current_time('mysql')
            ),
            array('id' => $download->id),
            array('%d', '%s'),
            array('%d')
        );
        
        return array('success' => true, 'download' => $download);
    }
    
    /**
     * Send download email
     */
    public static function send_download_email($email, $download_url) {
        $subject = __('Your CV Download Link - Digital Nomad Subscriptions', 'palmerita-subscriptions');
        
        $message = self::get_download_email_template($download_url);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Digital Nomad Subscriptions <hana@palmeratech.net>'
        );
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Get download email template
     */
    private static function get_download_email_template($download_url) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Your CV Download</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .download-btn { display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .download-btn:hover { background: #218838; }
                .info-box { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎯 Your CV Download is Ready!</h1>
                    <p>Thank you for your interest in my professional profile</p>
                </div>
                
                <div class="content">
                    <h2>Hello there! 👋</h2>
                    
                    <p>Thank you for requesting my CV. I'm excited about the possibility of working together!</p>
                    
                    <div style="text-align: center;">
                        <a href="<?php echo esc_url($download_url); ?>" class="download-btn">
                            👁️ View & Download CV
                        </a>
                    </div>
                    
                    <div class="info-box">
                        <strong>Important Information:</strong>
                        <ul>
                            <li>This viewing link is valid for <strong>7 days</strong></li>
                            <li>You can download the CV up to <strong>3 times</strong></li>
                            <li>View the CV directly in your browser with our integrated viewer</li>
                            <li>The link is unique and secure</li>
                            <li>If you have any issues, please contact me directly</li>
                        </ul>
                    </div>
                    
                    <h3>About Me</h3>
                    <p>I'm a passionate full-stack developer and WordPress specialist with over 10 years of experience in web development, specializing in WordPress, React, and modern web technologies.</p>
                    
                    <h3>Let's Connect!</h3>
                    <p>I'd love to discuss how I can help bring your project to life. Feel free to reach out:</p>
                    <ul>
                        <li>📧 Email: hana@palmeratech.net</li>
                        <li>🌐 Website: palmeratech.net</li>
                        <li>💼 LinkedIn: /in/hanaley-mosley/</li>
                    </ul>
                </div>
                
                <div class="footer">
                    <p>This email was sent because you requested to download my CV from palmeratech.net</p>
                    <p>If you didn't request this, you can safely ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send email with plugin ZIP download link
     */
    public static function send_zip_email($email, $download_url) {
        $subject = __('Your Plugin Download Link - Palmerita Subscriptions', 'palmerita-subscriptions');

        $message = self::get_zip_email_template($download_url);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Palmerita Productions <hello@palmeritaproductions.com>'
        );

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * HTML template for the ZIP email
     */
    private static function get_zip_email_template($download_url) {
        ob_start(); ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Your Plugin Download</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #111 0%, #6366f1 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .download-btn { display: inline-block; background: #111; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .download-btn:hover { background: #000; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🛠️ Your Plugin is Ready!</h1>
                    <p>Thank you for requesting Palmerita Subscriptions.</p>
                </div>
                <div class="content">
                    <p>Click the button below to download the ZIP package. The link is unique and expires in 7 days.</p>
                    <div style="text-align:center;">
                        <a href="<?php echo esc_url($download_url); ?>" class="download-btn">⬇️ Download Plugin</a>
                    </div>
                    <p>If you have any questions or need support, just reply to this email.</p>
                </div>
                <div class="footer">
                    <p>This email was sent because you requested the plugin from palmeritaproductions.com</p>
                </div>
            </div>
        </body>
        </html>
        <?php return ob_get_clean();
    }
    
    /**
     * Create downloads table
     */
    public static function create_downloads_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'palmerita_downloads';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean up expired downloads
     */
    public static function cleanup_expired_downloads() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'palmerita_downloads';
        
        $wpdb->query("
            UPDATE $table_name 
            SET status = 'expired' 
            WHERE expires < NOW() 
            AND status = 'active'
        ");
        
        // Delete old records (older than 30 days)
        $wpdb->query("
            DELETE FROM $table_name 
            WHERE created < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
} 