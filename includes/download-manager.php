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
            // Return tracking URL instead of direct URL
            return site_url('/palmerita-track/' . $token);
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
        $subject = __('Your document is ready! 📄', 'palmerita-subscriptions');
        
        $templates = get_option('palmerita_email_templates', array());
        if(!empty($templates['cv'])){
            $message = str_replace(array('{{download_url}}','{{subscriber_help}}'), array($download_url, $help_msg = get_option('palmerita_email_settings')['subscriber_help'] ?? ''), $templates['cv']);
        } else {
            $message = self::get_download_email_template($download_url);
        }
        
        // Use SMTP configuration from Email Settings instead of hardcoded From header
        $headers = array(
            'Content-Type: text/html; charset=UTF-8'
        );
        
        // Log email attempt for debugging
        error_log("Palmerita: Sending CV email to {$email}");
        
        $result = wp_mail($email, $subject, $message, $headers);
        do_action('palmerita_log_email_delivery', 'cv', $email, $subject, $result, $result ? '' : 'Error al enviar email');
        
        // Log result for debugging
        if (!$result) {
            error_log("Palmerita: Failed to send CV email to {$email}");
        } else {
            error_log("Palmerita: Successfully sent CV email to {$email}");
        }
        
        return $result;
    }
    
    /**
     * Get download email template
     */
    public static function get_download_email_template($download_url) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Your document is ready!</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .view-btn { display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .view-btn:hover { background: #5a67d8; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📄 Your document is ready!</h1>
                    <p>Thanks for reaching out!</p>
                </div>
                
                <div class="content">
                    <h2>Hey there! 👋</h2>
                    
                    <p>Thanks for your interest! I've prepared the document you requested and it's ready for you to view.</p>
                    
                    <div style="text-align: center;">
                        <a href="<?php echo esc_url($download_url); ?>" class="view-btn">
                            📄 View Document
                        </a>
                    </div>
                    
                    <p>The link above will take you to a secure viewer where you can access the document. It's valid for 7 days and you can access it up to 3 times.</p>
                    
                    <h3>Let's connect!</h3>
                    <p>I'd love to hear about your project. Feel free to reach out:</p>
                    <ul>
                        <li>📧 Email: hmosley@palmeratech.net</li>
                        <li>🌐 Website: palmeratech.net</li>
                        <li>💼 LinkedIn: /in/hanaley-mosley/</li>
                    </ul>
                    
                    <p style="margin-top: 30px;"><em>Looking forward to potentially working together!</em></p>
                    <p><strong>Hanaley</strong> ✨</p>

                    <?php 
                    $email_settings = get_option('palmerita_email_settings', array());
                    $help_msg = !empty($email_settings['subscriber_help']) ? esc_html($email_settings['subscriber_help']) : __('P.S. Can\'t find our email? Check your Spam or Promotions folder and drag it to your Inbox so you never miss future gifts or updates.', 'palmerita-subscriptions');
                    ?>
                    <p style="margin-top:25px;text-align:center;font-size:14px;color:#555;"><?php echo $help_msg; ?></p>
                </div>
                
                <div class="footer">
                    <p>You requested this document from palmeratech.net</p>
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
        $subject = __('Your files are ready! 📁', 'palmerita-subscriptions');

        $message = self::get_zip_email_template($download_url);

        // Use SMTP configuration from Email Settings instead of hardcoded From header
        $headers = array(
            'Content-Type: text/html; charset=UTF-8'
        );

        // Log email attempt for debugging
        error_log("Palmerita: Sending ZIP email to {$email}");
        
        $result = wp_mail($email, $subject, $message, $headers);
        do_action('palmerita_log_email_delivery', 'file', $email, $subject, $result, $result ? '' : 'Error al enviar email');
        
        // Log result for debugging
        if (!$result) {
            error_log("Palmerita: Failed to send ZIP email to {$email}");
        } else {
            error_log("Palmerita: Successfully sent ZIP email to {$email}");
        }
        
        return $result;
    }

    /**
     * Send welcome email to promotion subscribers
     */
    public static function send_promo_welcome_email($email) {
        $subject = __('Welcome to my updates! 🎯', 'palmerita-subscriptions');

        $message = self::get_promo_welcome_email_template();

        // Use SMTP configuration from Email Settings instead of hardcoded From header
        $headers = array(
            'Content-Type: text/html; charset=UTF-8'
        );

        // Log email attempt for debugging
        error_log("Palmerita: Sending promo welcome email to {$email}");
        
        $result = wp_mail($email, $subject, $message, $headers);
        do_action('palmerita_log_email_delivery', 'promo', $email, $subject, $result, $result ? '' : 'Error al enviar email');
        
        // Log result for debugging
        if (!$result) {
            error_log("Palmerita: Failed to send promo welcome email to {$email}");
        } else {
            error_log("Palmerita: Successfully sent promo welcome email to {$email}");
        }
        
        return $result;
    }

    /**
     * HTML template for the promo welcome email
     */
    public static function get_promo_welcome_email_template() {
        ob_start(); ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to my updates!</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .welcome-btn { display: inline-block; background: #f59e0b; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .welcome-btn:hover { background: #d97706; }
                .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .benefits { background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb; }
                .benefits h3 { color: #f59e0b; margin-top: 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎯 Welcome to my inner circle!</h1>
                    <p>Thank you for subscribing to my updates!</p>
                </div>
                
                <div class="content">
                    <h2>Hey there! 👋</h2>
                    
                    <p>I'm so excited you've joined my community! You've just become part of a select group of people who'll be the first to know about everything I'm working on.</p>
                    
                                         <div class="benefits">
                         <h3>Here's what you can expect from me:</h3>
                         <ul>
                             <li>🎁 <strong>Exclusive gifts and freebies</strong> - Premium resources just for my subscribers</li>
                             <li>💰 <strong>Important discounts</strong> - Save big on my services and products</li>
                             <li>📅 <strong>Monthly special offers</strong> - Limited-time deals delivered to your inbox</li>
                             <li>🚀 <strong>Early access</strong> to new projects and services before the public</li>
                             <li>🎯 <strong>Behind-the-scenes</strong> insights from my web development journey</li>
                             <li>🔥 <strong>Pro tips and tricks</strong> that I only share with my inner circle</li>
                             <li>📢 <strong>VIP announcements</strong> before anyone else knows</li>
                         </ul>
                     </div>
                    
                                         <div class="info-box">
                         <strong>A personal promise:</strong><br>
                         I respect your time and inbox. You'll receive valuable content including monthly offers, exclusive discounts, and special gifts. No spam, ever. Just meaningful updates and real value from one developer to another.
                     </div>
                    
                                         <h3>What's next?</h3>
                     <p>Keep an eye on your inbox! You'll start receiving monthly offers, exclusive discounts, and special gifts very soon. I've got some amazing deals and freebies brewing that I can't wait to share with you!</p>
                    
                    <div style="text-align: center;">
                        <a href="https://palmeratech.net" class="welcome-btn">
                            🌐 Visit My Website
                        </a>
                    </div>
                    
                    <h3>Let's Stay Connected!</h3>
                    <p>Feel free to reach out anytime. I love connecting with fellow developers and potential collaborators:</p>
                    <ul>
                        <li>📧 Email: hmosley@palmeratech.net</li>
                        <li>🌐 Website: palmeratech.net</li>
                        <li>💼 LinkedIn: /in/hanaley-mosley/</li>
                    </ul>
                    
                    <p style="margin-top: 30px;"><em>Thanks again for joining me on this journey!</em></p>
                    <p><strong>Hanaley</strong> ✨</p>

                    <?php 
                    $email_settings = get_option('palmerita_email_settings', array());
                    $help_msg = !empty($email_settings['subscriber_help']) ? esc_html($email_settings['subscriber_help']) : __('P.S. Can\'t find our email? Check your Spam or Promotions folder and drag it to your Inbox so you never miss future gifts or updates.', 'palmerita-subscriptions');
                    ?>
                    <p style="margin-top:25px;text-align:center;font-size:14px;color:#555;"><?php echo $help_msg; ?></p>
                </div>
                
                <div class="footer">
                    <p>You're receiving this because you subscribed to updates at palmeratech.net</p>
                    <p>If you didn't mean to subscribe, you can safely ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
        <?php return ob_get_clean();
    }

    /**
     * HTML template for the ZIP email
     */
    public static function get_zip_email_template($download_url) {
        ob_start(); ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Your files are ready!</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .access-btn { display: inline-block; background: #6366f1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .access-btn:hover { background: #5b21b6; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📁 Your files are ready!</h1>
                    <p>Thanks for your interest!</p>
                </div>
                <div class="content">
                    <h2>Hey! 👋</h2>
                    
                    <p>The files you requested are now ready for you. I've prepared everything you need.</p>
                    
                    <div style="text-align:center;">
                        <a href="<?php echo esc_url($download_url); ?>" class="access-btn">📁 Access Files</a>
                    </div>
                    
                    <p>This link will take you to a secure area where you can access your files. The link is valid for 7 days.</p>
                    
                    <p>If you have any questions or need help, just reply to this email.</p>
                    
                    <p style="margin-top: 30px;"><em>Thanks for checking out my work!</em></p>
                    <p><strong>Hanaley</strong> ✨</p>

                    <?php 
                    $email_settings = get_option('palmerita_email_settings', array());
                    $help_msg = !empty($email_settings['subscriber_help']) ? esc_html($email_settings['subscriber_help']) : __('P.S. Can\'t find our email? Check your Spam or Promotions folder and drag it to your Inbox so you never miss future gifts or updates.', 'palmerita-subscriptions');
                    ?>
                    <p style="margin-top:25px;text-align:center;font-size:14px;color:#555;"><?php echo $help_msg; ?></p>
                </div>
                <div class="footer">
                    <p>You requested these files from palmeratech.net</p>
                    <p>If you didn't request this, you can safely ignore this email.</p>
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
            -- Tracking fields
            clicked tinyint(1) DEFAULT 0,
            first_clicked datetime NULL,
            click_count int(11) DEFAULT 0,
            last_clicked datetime NULL,
            user_agent text NULL,
            ip_address varchar(45) NULL,
            referrer varchar(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY subscription_id (subscription_id),
            KEY email (email),
            KEY expires (expires),
            KEY clicked (clicked),
            KEY status (status)
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