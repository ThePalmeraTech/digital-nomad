<?php
/**
 * Plugin Name: Digital Nomad Subscriptions
 * Plugin URI: https://www.palmeratech.net
 * Description: Handles CV and promotion subscriptions with modals and database storage.
 * Version: 1.2.0
 * Author: Hanaley Mosley - Palmerita Productions
 * Author URI: https://www.palmeratech.net
 * Text Domain: palmerita-subscriptions
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants safely
if (!defined('PALMERITA_SUBS_VERSION')) {
define('PALMERITA_SUBS_VERSION', '1.2.0');
}
if (!defined('PALMERITA_SUBS_PLUGIN_FILE')) {
    define('PALMERITA_SUBS_PLUGIN_FILE', __FILE__);
}
if (!defined('PALMERITA_SUBS_PLUGIN_DIR')) {
define('PALMERITA_SUBS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('PALMERITA_SUBS_PLUGIN_URL')) {
define('PALMERITA_SUBS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// File path of ZIP plugin to share
if (!defined('PALMERITA_SHARE_ZIP')) {
    define('PALMERITA_SHARE_ZIP', PALMERITA_SUBS_PLUGIN_DIR . 'assets/zip/palmerita-subscriptions.zip');
}

// Include required files
require_once PALMERITA_SUBS_PLUGIN_DIR . 'includes/download-manager.php';

/**
 * Main Plugin Class
 */
class PalmeritaSubscriptions {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Defer initialization to a hook
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wp_ajax_palmerita_restore_template', array($this, 'ajax_restore_template'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Hook into WordPress
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_palmerita_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_nopriv_palmerita_subscribe', array($this, 'handle_subscription'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // URL rewrite rules for downloads
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_download_page'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add shortcodes for buttons
        add_shortcode('palmerita_subscription_buttons', array($this, 'render_subscription_buttons'));
        add_shortcode('palmerita_cv_button', array($this, 'render_cv_button'));
        add_shortcode('palmerita_promo_button', array($this, 'render_promo_button'));
        // File download button (alias, replacing older plugin_button)
        add_shortcode('palmerita_file_button', array($this, 'render_plugin_button'));
        // Backward compatibility (deprecated)
        add_shortcode('palmerita_plugin_button', array($this, 'render_plugin_button'));
        
        // Handle CSV exports early to avoid header already sent issues
        add_action('admin_init', array($this, 'maybe_export_csv'));
        add_action('admin_init', array($this, 'maybe_update_database'));
        
        // Hook PHPMailer to send via custom SMTP if configured
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));

        add_action('wp_ajax_palmerita_send_test_email', array($this, 'send_test_email'));

        add_action('palmerita_log_email_delivery', function($type, $to, $subject, $success, $error = '') {
            if (method_exists('PalmeritaSubscriptions', 'get_instance')) {
                $plugin = PalmeritaSubscriptions::get_instance();
                $plugin->log_email_delivery($type, $to, $subject, $success, $error);
            }
        }, 10, 5);
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('palmerita-subscriptions', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'palmerita-subs-frontend',
            PALMERITA_SUBS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            PALMERITA_SUBS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'palmerita-subs-frontend',
            PALMERITA_SUBS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PALMERITA_SUBS_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('palmerita-subs-frontend', 'palmerita_subs_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('palmerita_subs_nonce'),
            'messages' => array(
                'success_cv' => __('Thank you! We have sent a download link to your email address. Please check your spam folder if you don\'t see it in your inbox.', 'palmerita-subscriptions'),
                'success_promo' => __('Excellent! We will keep you informed about our promotions.', 'palmerita-subscriptions'),
                'error' => __('There was an error. Please try again.', 'palmerita-subscriptions'),
                'invalid_email' => __('Please enter a valid email address.', 'palmerita-subscriptions'),
                'required_email' => __('Email address is required.', 'palmerita-subscriptions')
            ),
            'recaptcha_site_key' => $this->recaptcha_enabled() ? get_option('palmerita_email_settings')['recaptcha_site_key'] : '',
            'modal_copy' => get_option('palmerita_modal_copy', array())
        ));

        // Enqueue Google reCAPTCHA script if enabled
        if ($this->recaptcha_enabled()) {
            $site_key = get_option('palmerita_email_settings')['recaptcha_site_key'];
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, array(), null, true);
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'palmerita-subscriptions') !== false) {
            wp_enqueue_style(
                'palmerita-subs-admin',
                PALMERITA_SUBS_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                PALMERITA_SUBS_VERSION
            );
            
            wp_enqueue_script(
                'palmerita-subs-admin',
                PALMERITA_SUBS_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                PALMERITA_SUBS_VERSION,
                true
            );
            
            // Load color enhancement script on modal copy page
            if (strpos($hook, 'palmerita-modal-copy') !== false) {
                wp_enqueue_script(
                    'palmerita-subs-admin-colors',
                    PALMERITA_SUBS_PLUGIN_URL . 'assets/js/admin-colors.js',
                array('jquery'),
                PALMERITA_SUBS_VERSION,
                true
            );
            }
        }

        if($hook === 'subscriptions_page_palmerita-email-templates'){
            wp_enqueue_code_editor(array('type'=>'text/html'));
            wp_enqueue_script('palmerita-email-templates', PALMERITA_SUBS_PLUGIN_URL.'assets/js/email-templates.js', array('jquery','code-editor'), '1.0', true);
            wp_enqueue_style('palmerita-email-templates', PALMERITA_SUBS_PLUGIN_URL.'assets/css/email-templates.css', array(), '1.0');
            // Pasar textos a JS
            $help_msg = get_option('palmerita_email_settings',array())['subscriber_help'] ?? __('P.S. Can\'t find our email? Check your Spam or Promotions folder and drag it to your Inbox so you never miss future gifts or updates.','palmerita-subscriptions');
            wp_localize_script('palmerita-email-templates','EmailTemplates',array(
                'i18n'=>array(
                    'defaultTemplate'=>__('This email will use the default built-in template.','palmerita-subscriptions'),
                    'helpMsg'=>$help_msg
                )
            ));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Palmerita Subscriptions', 'palmerita-subscriptions'),
            __('Subscriptions', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-subscriptions',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            30
        );
        
        add_submenu_page(
            'palmerita-subscriptions',
            __('CV Subscriptions', 'palmerita-subscriptions'),
            __('CV List', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-cv-list',
            array($this, 'cv_list_page')
        );
        
        add_submenu_page(
            'palmerita-subscriptions',
            __('Promo Subscriptions', 'palmerita-subscriptions'),
            __('Promo List', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-promo-list',
            array($this, 'promo_list_page')
        );
        
        add_submenu_page(
            'palmerita-subscriptions',
            __('File Downloads', 'palmerita-subscriptions'),
            __('File Downloads', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-file-list',
            array($this, 'file_list_page')
        );
        
        add_submenu_page(
            'palmerita-subscriptions',
            __('Terms & Conditions', 'palmerita-subscriptions'),
            __('Terms & Conditions', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-terms',
            array($this, 'terms_page')
        );
        
        add_submenu_page(
            'palmerita-subscriptions',
            __('CV Manager', 'palmerita-subscriptions'),
            __('CV Manager', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-cv-manager',
            array($this, 'cv_manager_page')
        );
        
        // File Manager (ZIP)
        add_submenu_page(
            'palmerita-subscriptions',
            __('File Manager', 'palmerita-subscriptions'),
            __('File Manager', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-file-manager',
            array($this, 'plugin_manager_page')
        );
        
        // Email / SMTP settings page
        add_submenu_page(
            'palmerita-subscriptions',
            __('Email Settings', 'palmerita-subscriptions'),
            __('Email Settings', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-email-settings',
            array($this, 'email_settings_page')
        );

        // Modal Copy settings
        add_submenu_page(
            'palmerita-subscriptions',
            __('Modal Copy', 'palmerita-subscriptions'),
            __('Modal Copy', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-modal-copy',
            array($this, 'modal_copy_page')
        );
        
        // Link Analytics
        add_submenu_page(
            'palmerita-subscriptions',
            __('Link Analytics', 'palmerita-subscriptions'),
            __('Link Analytics', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-link-analytics',
            array($this, 'link_analytics_page')
        );
        
        // Database Upgrade (only show if upgrade is needed)
        $tracking_version = get_option('palmerita_tracking_version', '0');
        if (version_compare($tracking_version, '1.1.0', '<')) {
            add_submenu_page(
                'palmerita-subscriptions',
                __('Upgrade Tracking', 'palmerita-subscriptions'),
                __('🔄 Upgrade Tracking', 'palmerita-subscriptions'),
                'manage_options',
                'palmerita-upgrade-tracking',
                array($this, 'upgrade_tracking_page')
            );
        }

        // Email Templates editor
        add_submenu_page(
            'palmerita-subscriptions',
            __('Email Templates', 'palmerita-subscriptions'),
            __('Email Templates', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-email-templates',
            array($this, 'email_templates_page')
        );

        // Email Log
        add_submenu_page(
            'palmerita-subscriptions',
            __('Email Log', 'palmerita-subscriptions'),
            __('Email Log', 'palmerita-subscriptions'),
            'manage_options',
            'palmerita-email-log',
            array($this, 'email_log_page')
        );
    }
    
    /**
     * Handle AJAX subscription
     */
    public function handle_subscription() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'palmerita_subs_nonce')) {
            wp_die(__('Security check failed', 'palmerita-subscriptions'));
        }
        
        // Honeypot (simple bot trap)
        if (!empty($_POST['pal_subs_hp'])) {
            wp_send_json_error(__('Spam detected.', 'palmerita-subscriptions'));
        }

        // Basic rate-limit by IP: 5 attempts / 10 minutes
        $ip_key = 'pal_subs_rl_' . md5($_SERVER['REMOTE_ADDR']);
        $attempts = intval(get_transient($ip_key));
        if ($attempts >= 5) {
            wp_send_json_error(__('Too many attempts. Please try again later.', 'palmerita-subscriptions'));
        }
        
        $email = sanitize_email($_POST['email']);
        $type = sanitize_text_field($_POST['type']);
        
        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address', 'palmerita-subscriptions'));
        }
        
        if (!in_array($type, array('cv', 'promo', 'plugin'))) {
            wp_send_json_error(__('Invalid subscription type', 'palmerita-subscriptions'));
        }
        
        // Verify reCAPTCHA if enabled
        if ($this->recaptcha_enabled()) {
            $token = isset($_POST['recaptcha_token']) ? sanitize_text_field($_POST['recaptcha_token']) : '';
            if (empty($token)) {
                wp_send_json_error(__('reCAPTCHA verification failed.', 'palmerita-subscriptions'));
            }

            $settings = get_option('palmerita_email_settings');
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array(
                    'secret' => $settings['recaptcha_secret'],
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                )
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(__('reCAPTCHA request failed.', 'palmerita-subscriptions'));
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $score = isset($body['score']) ? floatval($body['score']) : 0;
            $threshold = isset($settings['recaptcha_threshold']) ? floatval($settings['recaptcha_threshold']) : 0.3;

            if (empty($body['success']) || $score < $threshold) {
                wp_send_json_error(__('reCAPTCHA verification failed.', 'palmerita-subscriptions'));
            }
        }
        
        // Save to database
        $result = $this->save_subscription($email, $type);
        
        if ($result) {
            // increment rate limit counter
            set_transient($ip_key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
            
            // If CV subscription, generate download link and send email
            if ($type === 'cv') {
                $subscription_id = $result;
                $download_url = PalmeritaDownloadManager::generate_download_link($email, $subscription_id);
                
                if ($download_url) {
                    error_log("Palmerita: Generated CV download URL for {$email}: {$download_url}");
                    
                    // Add wp_mail error logging temporarily
                    add_action('wp_mail_failed', array($this, 'log_wp_mail_error'));
                    
                    $email_sent = PalmeritaDownloadManager::send_download_email($email, $download_url);
                    
                    remove_action('wp_mail_failed', array($this, 'log_wp_mail_error'));
                    
                    if (!$email_sent) {
                        error_log('Palmerita: CRITICAL - Failed to send CV email to ' . $email . '. Check SMTP configuration.');
                        // Still return success to user, but log the error
                    } else {
                        error_log('Palmerita: CV email sent successfully to ' . $email);
                    }
                } else {
                    error_log('Palmerita: CRITICAL - Failed to generate download URL for ' . $email);
                }
            } elseif ($type === 'plugin') {
                $subscription_id = $result;
                $download_url = PalmeritaDownloadManager::generate_download_link($email, $subscription_id);
                if ($download_url) {
                    error_log("Palmerita: Generated plugin download URL for {$email}: {$download_url}");
                    
                    // Add wp_mail error logging temporarily
                    add_action('wp_mail_failed', array($this, 'log_wp_mail_error'));
                    
                    $email_sent = PalmeritaDownloadManager::send_zip_email($email, $download_url);
                    
                    remove_action('wp_mail_failed', array($this, 'log_wp_mail_error'));
                    
                    if (!$email_sent) {
                        error_log('Palmerita: CRITICAL - Failed to send plugin email to ' . $email . '. Check SMTP configuration.');
                        // Still return success to user, but log the error
                    } else {
                        error_log('Palmerita: Plugin email sent successfully to ' . $email);
                    }
                } else {
                    error_log('Palmerita: CRITICAL - Failed to generate download URL for ' . $email);
                }
            } elseif ($type === 'promo') {
                // Send welcome email to promotion subscribers
                error_log("Palmerita: Sending promo welcome email to {$email}");
                
                // Add wp_mail error logging temporarily
                add_action('wp_mail_failed', array($this, 'log_wp_mail_error'));
                
                $email_sent = PalmeritaDownloadManager::send_promo_welcome_email($email);
                
                remove_action('wp_mail_failed', array($this, 'log_wp_mail_error'));
                
                if (!$email_sent) {
                    error_log('Palmerita: CRITICAL - Failed to send promo welcome email to ' . $email . '. Check SMTP configuration.');
                    // Still return success to user, but log the error
                } else {
                    error_log('Palmerita: Promo welcome email sent successfully to ' . $email);
                }
            }
            
            wp_send_json_success(array(
                'message' => $type === 'cv' ? 
                    __('Thank you! We have sent a secure viewing link to your email address. You can view and download my CV directly in your browser. Please check your spam folder if you don\'t see it in your inbox.', 'palmerita-subscriptions') :
                    ($type === 'promo' ? __('Awesome! I\'ve sent you a welcome email with all the details. You\'re now part of my inner circle and will be the first to know about exciting updates!', 'palmerita-subscriptions') : __('Thank you! We have emailed you the download link for the plugin.', 'palmerita-subscriptions'))
            ));
        } else {
            wp_send_json_error(__('Error saving subscription', 'palmerita-subscriptions'));
        }
    }
    
    /**
     * Save subscription to database
     */
    private function save_subscription($email, $type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'palmerita_subscriptions';
        
        // --- Safety check: create table on the fly if missing ---
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            // Attempt to (re)create the table – this avoids fatal errors on new clones/migrations
            $this->create_database_tables();
        }
        
        // Check if email already exists for this type
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE email = %s AND type = %s",
            $email, $type
        ));
        
        if ($existing) {
            return $existing; // Return existing ID
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'type' => $type,
                'date_created' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Render subscription buttons shortcode
     */
    public function render_subscription_buttons($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default'
        ), $atts, 'palmerita_subscription_buttons');
        
        $copy = get_option('palmerita_modal_copy', array());

        // Defaults
        $defaults = array(
            'cv_btn_text'    => __('Get my CV','palmerita-subscriptions'),
            'cv_btn_icon'    => '📄',
            'cv_btn_color'   => '',
            'promo_btn_text' => __('PROMOTIONS','palmerita-subscriptions'),
            'promo_btn_icon' => '🎯',
            'promo_btn_color'=> '',
            'file_btn_text'  => __('Download File','palmerita-subscriptions'),
            'file_btn_icon'  => '🗂️',
            'file_btn_color' => '',
            'cv_text_color' => '',
            'promo_text_color'=> '',
            'file_text_color'=> '',
        );

        $cfg = array_merge($defaults,$copy);

        // Helper to build style with proper fallbacks
        $style_attr = function($bg,$txt=''){
            $css = '';
            if($bg && $bg !== '')  $css .= 'background-color:'.esc_attr($bg).'; background-image: none; ';
            if($txt && $txt !== '') $css .= 'color:'.esc_attr($txt).' !important;';
            return $css ? 'style="'.$css.'"' : '';
        };
        
        ob_start();
        ?>
        <div class="palmerita-subscription-buttons" data-style="<?php echo esc_attr($atts['style']); ?>">
            <button type="button" class="palmerita-btn palmerita-btn-cv" data-type="cv" data-palmerita-btn="1" <?php echo $style_attr($cfg['cv_btn_color'],$cfg['cv_text_color']); ?> >
                <?php if($cfg['cv_btn_icon']) : ?><span class="btn-icon"><?php echo esc_html($cfg['cv_btn_icon']); ?></span><?php endif; ?>
                <span class="btn-text"><?php echo esc_html($cfg['cv_btn_text']); ?></span>
            </button>
            
            <button type="button" class="palmerita-btn palmerita-btn-promo" data-type="promo" data-palmerita-btn="1" <?php echo $style_attr($cfg['promo_btn_color'],$cfg['promo_text_color']); ?> >
                <?php if($cfg['promo_btn_icon']) : ?><span class="btn-icon"><?php echo esc_html($cfg['promo_btn_icon']); ?></span><?php endif; ?>
                <span class="btn-text"><?php echo esc_html($cfg['promo_btn_text']); ?></span>
            </button>

            <button type="button" class="palmerita-btn palmerita-btn-plugin" data-type="plugin" data-palmerita-btn="1" <?php echo $style_attr($cfg['file_btn_color'],$cfg['file_text_color']); ?> >
                <?php if($cfg['file_btn_icon']) : ?><span class="btn-icon"><?php echo esc_html($cfg['file_btn_icon']); ?></span><?php endif; ?>
                <span class="btn-text"><?php echo esc_html($cfg['file_btn_text']); ?></span>
            </button>
        </div>
        
        <!-- Modal -->
        <div id="palmerita-subscription-modal" class="palmerita-modal">
            <div class="palmerita-modal-content">
                <div class="palmerita-modal-header">
                    <h3 id="palmerita-modal-title"></h3>
                    <button type="button" class="palmerita-modal-close">&times;</button>
                </div>
                <div class="palmerita-modal-body">
                    <p id="palmerita-modal-description"></p>
                    <form id="palmerita-subscription-form">
                        <div class="form-group">
                            <label for="palmerita-email"><?php _e('Email Address:', 'palmerita-subscriptions'); ?></label>
                            <input type="email" id="palmerita-email" name="email" required placeholder="your@email.com">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="palmerita-btn-submit">
                                <span class="btn-text"><?php _e('Subscribe', 'palmerita-subscriptions'); ?></span>
                                <span class="btn-loading" style="display: none;">⏳</span>
                            </button>
                            <button type="button" class="palmerita-btn-cancel"><?php _e('Cancel', 'palmerita-subscriptions'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Admin main page
     */
    public function admin_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/main-page.php';
    }
    
    /**
     * CV list admin page
     */
    public function cv_list_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/cv-list.php';
    }
    
    /**
     * Promo list admin page
     */
    public function promo_list_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/promo-list.php';
    }
    
    /**
     * File downloads list admin page
     */
    public function file_list_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/file-list.php';
    }
    
    /**
     * Link analytics admin page
     */
    public function link_analytics_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/link-analytics.php';
    }
    
    /**
     * Database upgrade admin page
     */
    public function upgrade_tracking_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/upgrade-tracking.php';
    }
    
    /**
     * Terms & Conditions admin page
     */
    public function terms_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/terms-conditions.php';
    }
    
    /**
     * CV Manager admin page
     */
    public function cv_manager_page() {
        include PALMERITA_SUBS_PLUGIN_DIR . 'admin/cv-manager.php';
    }
    
    /**
     * File manager page (upload ZIP)
     */
    public function plugin_manager_page() {
        // Handle file deletion
        if (isset($_POST['delete_file_nonce']) && wp_verify_nonce($_POST['delete_file_nonce'], 'delete_file')) {
            $current_file_url = get_option('palmerita_file_zip_url');
            if ($current_file_url) {
                // Delete physical file from files directory
                $upload_dir = wp_upload_dir();
                $files_url = $upload_dir['baseurl'] . '/files';
                
                if (strpos($current_file_url, $files_url) !== false) {
                    $file_path = str_replace($files_url, $upload_dir['basedir'] . '/files', $current_file_url);
                    if (file_exists($file_path)) {
                        wp_delete_file($file_path);
                    }
                }
                // Clear both options
                delete_option('palmerita_file_zip_url');
                delete_option('palmerita_plugin_zip_url');
                delete_option('palmerita_file_upload_date');
                delete_option('palmerita_file_original_name');
                echo '<div class="notice notice-success is-dismissible"><p>' . __('File deleted successfully.', 'palmerita-subscriptions') . '</p></div>';
            }
        }

        // Handle file upload/replacement
        if (isset($_POST['upload_file_nonce']) && wp_verify_nonce($_POST['upload_file_nonce'], 'upload_file')) {
            if (!empty($_FILES['new_file']['name'])) {
                $file = $_FILES['new_file'];
                
                // Validate file type
                $allowed_types = array('zip', 'pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png');
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_types)) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . 
                         sprintf(__('Invalid file type. Allowed: %s', 'palmerita-subscriptions'), implode(', ', $allowed_types)) . 
                         '</p></div>';
                } else {
                    // Delete old file first from files directory
                    $old_file_url = get_option('palmerita_file_zip_url');
                    if ($old_file_url) {
                        $upload_dir = wp_upload_dir();
                        $files_url = $upload_dir['baseurl'] . '/files';
                        
                        if (strpos($old_file_url, $files_url) !== false) {
                            $old_file_path = str_replace($files_url, $upload_dir['basedir'] . '/files', $old_file_url);
                            if (file_exists($old_file_path)) {
                                wp_delete_file($old_file_path);
                            }
                        }
                    }

                    // Create files directory if it doesn't exist
                    $upload_dir = wp_upload_dir();
                    $files_dir = $upload_dir['basedir'] . '/files';
                    $files_url = $upload_dir['baseurl'] . '/files';
                    
                    if (!file_exists($files_dir)) {
                        wp_mkdir_p($files_dir);
                    }

                    // Upload new file to files directory
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    $overrides = array(
                        'test_form' => false,
                        'upload_path' => $files_dir,
                        'unique_filename_callback' => function($dir, $name, $ext) {
                            // Force unique filename with timestamp
                            $timestamp = current_time('timestamp');
                            $clean_name = sanitize_file_name(pathinfo($name, PATHINFO_FILENAME));
                            return $clean_name . '_' . $timestamp . $ext;
                        }
                    );
                    
                    // Temporarily change upload directory
                    add_filter('upload_dir', function($dirs) use ($files_dir, $files_url) {
                        $dirs['path'] = $files_dir;
                        $dirs['url'] = $files_url;
                        $dirs['subdir'] = '/files';
                        return $dirs;
                    });
                    
                    $movefile = wp_handle_upload($file, $overrides);
                    
                    // Remove the upload directory filter
                    remove_all_filters('upload_dir');
                    
                    if ($movefile && empty($movefile['error'])) {
                        // Store the new file URL in both options for compatibility
                        update_option('palmerita_file_zip_url', $movefile['url']);
                        update_option('palmerita_plugin_zip_url', $movefile['url']);
                        
                        // Store additional metadata
                        update_option('palmerita_file_upload_date', current_time('mysql'));
                        update_option('palmerita_file_original_name', $file['name']);
                        
                        echo '<div class="notice notice-success is-dismissible"><p>' . 
                             sprintf(__('File "%s" uploaded successfully!', 'palmerita-subscriptions'), $file['name']) . 
                             '</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($movefile['error']) . '</p></div>';
                    }
                    }
                } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Please select a file to upload.', 'palmerita-subscriptions') . '</p></div>';
            }
        }

        // Get current file info
        $current_file_url = get_option('palmerita_file_zip_url');
        $upload_date = get_option('palmerita_file_upload_date');
        $original_name = get_option('palmerita_file_original_name');
        
        ?>
        <div class="wrap">
            <h1><?php _e('File Manager', 'palmerita-subscriptions'); ?></h1>
            <p><?php _e('Upload and manage the file that users will receive via secure download links.', 'palmerita-subscriptions'); ?></p>

            <?php if ($current_file_url): ?>
                <!-- Current File Section -->
                <div class="file-manager-current">
                    <h2><?php _e('Current File', 'palmerita-subscriptions'); ?></h2>
                    <div class="current-file-card">
                        <div class="file-info">
                            <div class="file-icon">📁</div>
                            <div class="file-details">
                                <h3><?php echo esc_html($original_name ?: basename($current_file_url)); ?></h3>
                                <p><strong><?php _e('URL:', 'palmerita-subscriptions'); ?></strong> 
                                   <a href="<?php echo esc_url($current_file_url); ?>" target="_blank"><?php echo esc_html(basename($current_file_url)); ?></a>
                                </p>
                                <?php if ($upload_date): ?>
                                    <p><strong><?php _e('Uploaded:', 'palmerita-subscriptions'); ?></strong> 
                                       <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($upload_date))); ?>
                                    </p>
                                <?php endif; ?>
                                <p><strong><?php _e('File Size:', 'palmerita-subscriptions'); ?></strong> 
                                   <?php 
                                   $upload_dir = wp_upload_dir();
                                   $files_url = $upload_dir['baseurl'] . '/files';
                                   
                                   if (strpos($current_file_url, $files_url) !== false) {
                                       $file_path = str_replace($files_url, $upload_dir['basedir'] . '/files', $current_file_url);
                                       if (file_exists($file_path)) {
                                           echo size_format(filesize($file_path));
                                       } else {
                                           echo '<span style="color: red;">' . __('File not found on server', 'palmerita-subscriptions') . '</span>';
                                       }
                                   } else {
                                       echo '<span style="color: orange;">' . __('File not in files directory', 'palmerita-subscriptions') . '</span>';
                                   }
                                   ?>
                                </p>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="<?php echo esc_url($current_file_url); ?>" class="button button-secondary" target="_blank">
                                👁️ <?php _e('Preview/Download', 'palmerita-subscriptions'); ?>
                            </a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('<?php _e('Are you sure you want to delete this file? This action cannot be undone.', 'palmerita-subscriptions'); ?>')">
                                <?php wp_nonce_field('delete_file', 'delete_file_nonce'); ?>
                                <button type="submit" class="button button-link-delete">
                                    🗑️ <?php _e('Delete File', 'palmerita-subscriptions'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <hr style="margin: 30px 0;">

                <!-- Replace File Section -->
                <h2><?php _e('Replace Current File', 'palmerita-subscriptions'); ?></h2>
                <div class="upload-notice">
                    <p><strong><?php _e('Note:', 'palmerita-subscriptions'); ?></strong> <?php _e('Uploading a new file will automatically delete the current file and update all future download links.', 'palmerita-subscriptions'); ?></p>
                </div>
            <?php else: ?>
                <!-- No File Section -->
                <div class="no-file-notice">
                    <div class="notice notice-warning">
                        <p><strong><?php _e('No file uploaded yet.', 'palmerita-subscriptions'); ?></strong></p>
                        <p><?php _e('Upload a file below to start sharing it via secure download links.', 'palmerita-subscriptions'); ?></p>
                    </div>
                </div>
                <h2><?php _e('Upload File', 'palmerita-subscriptions'); ?></h2>
            <?php endif; ?>

            <!-- Upload Form -->
            <form method="post" enctype="multipart/form-data" class="upload-form">
                <?php wp_nonce_field('upload_file', 'upload_file_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Select File', 'palmerita-subscriptions'); ?></th>
                        <td>
                            <input type="file" name="new_file" id="new_file" accept=".zip,.pdf,.doc,.docx,.txt,.jpg,.jpeg,.png" required />
                            <p class="description">
                                <?php _e('Allowed file types: ZIP, PDF, DOC, DOCX, TXT, JPG, JPEG, PNG', 'palmerita-subscriptions'); ?>
                                <br><?php _e('Maximum file size:', 'palmerita-subscriptions'); ?> <?php echo size_format(wp_max_upload_size()); ?>
                            </p>
                    </td>
                </tr>
                </table>
                <?php submit_button($current_file_url ? __('Replace File', 'palmerita-subscriptions') : __('Upload File', 'palmerita-subscriptions'), 'primary', 'submit', false); ?>
            </form>

            <!-- Help Section -->
            <div class="file-manager-help">
                <h3><?php _e('How it works', 'palmerita-subscriptions'); ?></h3>
                <ul>
                    <li><?php _e('Users request the file using the download button on your website', 'palmerita-subscriptions'); ?></li>
                    <li><?php _e('They receive a secure email with a unique download link', 'palmerita-subscriptions'); ?></li>
                    <li><?php _e('The link expires after 7 days and allows up to 3 downloads', 'palmerita-subscriptions'); ?></li>
                    <li><?php _e('All download activity is tracked in the Link Analytics section', 'palmerita-subscriptions'); ?></li>
                </ul>
        </div>
        </div>

        <style>
        .file-manager-current {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .current-file-card {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: white;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e1e5e9;
        }
        .file-info {
            display: flex;
            align-items: flex-start;
            flex: 1;
        }
        .file-icon {
            font-size: 48px;
            margin-right: 20px;
            opacity: 0.7;
        }
        .file-details h3 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        .file-details p {
            margin: 5px 0;
            color: #666;
        }
        .file-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-left: 20px;
        }
        .upload-form {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 20px;
        }
        .upload-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .no-file-notice {
            margin: 20px 0;
        }
        .file-manager-help {
            margin-top: 30px;
            padding: 20px;
            background: #f1f1f1;
            border-radius: 6px;
        }
        .file-manager-help ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .file-manager-help li {
            margin: 8px 0;
            color: #555;
        }
        </style>
        <?php
    }
    
    /**
     * Helper: current plugin ZIP URL
     */
    private function get_plugin_zip_url() {
        // Priorizar la opción consistente con la plantilla
        $stored = get_option('palmerita_file_zip_url');
        if (!$stored) {
        $stored = get_option('palmerita_plugin_zip_url');
        }
        
        // If we have a stored URL, make sure it's in the files directory
        if ($stored) {
            $upload_dir = wp_upload_dir();
            $files_url = $upload_dir['baseurl'] . '/files';
            
            // If the stored URL is not in files directory, check if file exists there
            if (strpos($stored, '/files/') === false) {
                $filename = basename($stored);
                $files_file_url = $files_url . '/' . $filename;
                $files_file_path = $upload_dir['basedir'] . '/files/' . $filename;
                
                if (file_exists($files_file_path)) {
                    // Update the stored URL to point to files directory
                    update_option('palmerita_file_zip_url', $files_file_url);
                    update_option('palmerita_plugin_zip_url', $files_file_url);
                    return $files_file_url;
                }
            }
            return $stored;
        }
        
        return PALMERITA_SUBS_PLUGIN_URL . 'assets/zip/palmerita-subscriptions.zip';
    }
    
    /**
     * Add URL rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^palmerita-download/([^/]+)/?$',
            'index.php?palmerita_download=1&download_token=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^palmerita-terms/(cv|promo)/?$',
            'index.php?palmerita_terms=1&terms_type=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^palmerita-cv-viewer/([^/]+)/?$',
            'index.php?palmerita_cv_viewer=1&viewer_token=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^palmerita-plugin-download/([^/]+)/?$',
            'index.php?palmerita_plugin_download=1&download_token=$matches[1]',
            'top'
        );
        
        // Track link clicks before redirecting to actual content
        add_rewrite_rule(
            '^palmerita-track/([^/]+)/?$',
            'index.php?palmerita_track=1&track_token=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'palmerita_download';
        $vars[] = 'download_token';
        $vars[] = 'palmerita_terms';
        $vars[] = 'terms_type';
        $vars[] = 'palmerita_cv_viewer';
        $vars[] = 'viewer_token';
        $vars[] = 'palmerita_plugin_download';
        $vars[] = 'palmerita_track';
        $vars[] = 'track_token';
        return $vars;
    }
    
    /**
     * Handle download page requests
     */
    public function handle_download_page() {
        if (get_query_var('palmerita_download')) {
            include PALMERITA_SUBS_PLUGIN_DIR . 'templates/download-page.php';
            exit;
        }
        
        if (get_query_var('palmerita_terms')) {
            $this->handle_terms_page();
            exit;
        }
        
        if (get_query_var('palmerita_cv_viewer')) {
            include PALMERITA_SUBS_PLUGIN_DIR . 'templates/cv-viewer.php';
            exit;
        }

        if (get_query_var('palmerita_plugin_download')) {
            include PALMERITA_SUBS_PLUGIN_DIR . 'templates/plugin-download-page.php';
            exit;
        }
        
        if (get_query_var('palmerita_track')) {
            $this->handle_track_click();
            exit;
        }
    }
    
    /**
     * Handle terms page display
     */
    private function handle_terms_page() {
        $terms_type = get_query_var('terms_type');
        
        if (!in_array($terms_type, array('cv', 'promo'))) {
            wp_die(__('Invalid terms type', 'palmerita-subscriptions'));
        }
        
        $terms_content = $terms_type === 'cv' 
            ? get_option('palmerita_cv_terms', '') 
            : get_option('palmerita_promo_terms', '');
        
        get_header();
        ?>
        <div class="palmerita-terms-page">
            <div class="container">
                <h1><?php echo $terms_type === 'cv' ? __('CV Download Terms', 'palmerita-subscriptions') : __('Promotions Terms', 'palmerita-subscriptions'); ?></h1>
                <div class="terms-content">
                    <?php echo wp_kses_post($terms_content); ?>
                </div>
                <div class="terms-actions">
                    <a href="<?php echo home_url(); ?>" class="btn btn-primary">
                        <?php _e('Back to Homepage', 'palmerita-subscriptions'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .palmerita-terms-page {
            padding: 40px 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .terms-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .terms-actions {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
        .btn:hover {
            background: #0056b3;
            color: white;
        }
        </style>
        <?php
        get_footer();
    }
    
    /**
     * Handle link click tracking and redirect
     */
    private function handle_track_click() {
        $token = get_query_var('track_token');
        
        if (!$token) {
            wp_die(__('Invalid tracking link', 'palmerita-subscriptions'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'palmerita_downloads';
        
        // Get download record
        $download = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
        
        if (!$download) {
            wp_die(__('Invalid or non-existent link', 'palmerita-subscriptions'));
        }
        
        // Check if link is still valid
        if (strtotime($download->expires) < current_time('timestamp')) {
            wp_die(__('This link has expired. Please request a new one.', 'palmerita-subscriptions'));
        }
        
        // Track the click
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip_address = $this->get_client_ip();
        $referrer = sanitize_text_field($_SERVER['HTTP_REFERER'] ?? '');
        
        $update_data = array(
            'click_count' => $download->click_count + 1,
            'last_clicked' => current_time('mysql'),
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'referrer' => $referrer
        );
        
        // Set first click time if this is the first click
        if (!$download->clicked) {
            $update_data['clicked'] = 1;
            $update_data['first_clicked'] = current_time('mysql');
        }
        
        // Update tracking data
        $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $download->id),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        // Log for debugging
        error_log("Palmerita: Tracked click for token {$token} by {$download->email} from IP {$ip_address}");
        
        // Redirect based on subscription type
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT type FROM {$wpdb->prefix}palmerita_subscriptions WHERE id = %d",
            $download->subscription_id
        ));
        
        if ($subscription && $subscription->type === 'plugin') {
            // Redirect to plugin download page
            wp_redirect(site_url('/palmerita-plugin-download/' . $token));
        } else {
            // Redirect to CV viewer
            wp_redirect(site_url('/palmerita-cv-viewer/' . $token));
        }
        exit;
        }
        
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_tables();
        PalmeritaDownloadManager::create_downloads_table();
        $this->update_database_for_tracking();
        flush_rewrite_rules();
    }
    
    /**
     * Update database to add tracking fields
     */
    private function update_database_for_tracking() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'palmerita_downloads';
        
        // Check if tracking columns exist, if not add them
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        if (!in_array('clicked', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN clicked tinyint(1) DEFAULT 0");
        }
        
        if (!in_array('first_clicked', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN first_clicked datetime NULL");
        }
        
        if (!in_array('click_count', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN click_count int(11) DEFAULT 0");
        }
        
        if (!in_array('last_clicked', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN last_clicked datetime NULL");
        }
        
        if (!in_array('user_agent', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN user_agent text NULL");
        }
        
        if (!in_array('ip_address', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN ip_address varchar(45) NULL");
        }
        
        if (!in_array('referrer', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN referrer varchar(255) NULL");
        }
        
        // Add indexes for better performance
        $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_clicked (clicked)");
        $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_status (status)");
        
        error_log('Palmerita: Database updated with tracking fields');
    }
    
    /**
     * Check if database needs to be updated for tracking
     */
    public function maybe_update_database() {
        $tracking_version = get_option('palmerita_tracking_version', '0');
        
        if (version_compare($tracking_version, '1.1.0', '<')) {
            $this->update_database_for_tracking();
            update_option('palmerita_tracking_version', '1.1.0');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'palmerita_subscriptions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            type varchar(20) NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            ip_address varchar(45),
            status varchar(20) DEFAULT 'active' NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_type (email, type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Render CV button shortcode
     */
    public function render_cv_button($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'text'  => '',
        ), $atts, 'palmerita_cv_button');

        // Override with saved copy if available
        $copy = get_option('palmerita_modal_copy', array());
        $btn_text  = !empty($copy['cv_btn_text'])  ? $copy['cv_btn_text']  : ( $atts['text'] ?: __('Get my CV','palmerita-subscriptions') );
        $btn_icon  = isset($copy['cv_btn_icon'])   ? $copy['cv_btn_icon']  : '';
        $btn_color = !empty($copy['cv_btn_color']) ? $copy['cv_btn_color'] : '';
        $btn_text_color = !empty($copy['cv_text_color']) ? $copy['cv_text_color'] : '';

        $style_attr = ($btn_color || $btn_text_color) ? 'style="'.($btn_color ? 'background-color:'.esc_attr($btn_color).'; background-image: none; ' : '').($btn_text_color ? 'color:'.esc_attr($btn_text_color).' !important;' : '').'"' : '';
        $style_class = 'palmerita-btn--' . esc_attr($atts['style']);
        ob_start();
        ?>
        <div class="palmerita-subscription-buttons single-button" data-style="<?php echo esc_attr($atts['style']); ?>">
            <button type="button" class="palmerita-btn <?php echo $style_class; ?> palmerita-btn-cv" data-type="cv" data-palmerita-btn="1" <?php echo $style_attr; ?> >
                <?php if($btn_icon){ ?>
                    <span class="btn-icon"><?php echo esc_html($btn_icon); ?></span>
                <?php } else { ?>
                <span class="btn-icon" aria-hidden="true">
                  <!-- SVG: Document icon -->
                  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="2" width="12" height="16" rx="2" fill="currentColor" fill-opacity="0.08"/><rect x="6" y="4" width="8" height="12" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M8 8h4M8 11h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                </span>
                <?php } ?>
                <span class="btn-text"><?php echo esc_html($btn_text); ?></span>
            </button>
        </div>
        <!-- Modal -->
        <div id="palmerita-subscription-modal" class="palmerita-modal">
            <div class="palmerita-modal-content">
                <div class="palmerita-modal-header">
                    <h3 id="palmerita-modal-title"></h3>
                    <button type="button" class="palmerita-modal-close">&times;</button>
                </div>
                <div class="palmerita-modal-body">
                    <p id="palmerita-modal-description"></p>
                    <form id="palmerita-subscription-form">
                        <div class="form-group">
                            <label for="palmerita-email"><?php _e('Email Address:', 'palmerita-subscriptions'); ?></label>
                            <input type="email" id="palmerita-email" name="email" required placeholder="your@email.com">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="palmerita-btn-submit">
                                <span class="btn-text"><?php _e('Send', 'palmerita-subscriptions'); ?></span>
                                <span class="btn-loading" style="display: none;">⏳</span>
                            </button>
                            <button type="button" class="palmerita-btn-cancel"><?php _e('Cancel', 'palmerita-subscriptions'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Promo button shortcode
     */
    public function render_promo_button($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'text'  => '',
        ), $atts, 'palmerita_promo_button');

        $copy = get_option('palmerita_modal_copy', array());
        $btn_text  = !empty($copy['promo_btn_text']) ? $copy['promo_btn_text'] : ( $atts['text'] ?: __('PROMOTIONS','palmerita-subscriptions') );
        $btn_icon  = isset($copy['promo_btn_icon'])  ? $copy['promo_btn_icon']  : '';
        $btn_color = !empty($copy['promo_btn_color'])? $copy['promo_btn_color']: '';
        $btn_text_color = !empty($copy['promo_text_color']) ? $copy['promo_text_color'] : '';
        $style_attr = ($btn_color || $btn_text_color) ? 'style="'.($btn_color ? 'background-color:'.esc_attr($btn_color).'; background-image: none; ' : '').($btn_text_color ? 'color:'.esc_attr($btn_text_color).' !important;' : '').'"' : '';
        $style_class = 'palmerita-btn--' . esc_attr($atts['style']);
        ob_start();
        ?>
        <div class="palmerita-subscription-buttons single-button" data-style="<?php echo esc_attr($atts['style']); ?>">
            <button type="button" class="palmerita-btn <?php echo $style_class; ?> palmerita-btn-promo" data-type="promo" data-palmerita-btn="1" <?php echo $style_attr; ?> >
                <?php if($btn_icon){ ?>
                    <span class="btn-icon"><?php echo esc_html($btn_icon); ?></span>
                <?php } else { ?>
                <span class="btn-icon" aria-hidden="true">
                  <!-- SVG: Target icon -->
                  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5" fill="currentColor" fill-opacity="0.08"/><circle cx="10" cy="10" r="4" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="1.5" fill="currentColor"/></svg>
                </span>
                <?php } ?>
                <span class="btn-text"><?php echo esc_html($btn_text); ?></span>
            </button>
        </div>
        <!-- Modal -->
        <div id="palmerita-subscription-modal" class="palmerita-modal">
            <div class="palmerita-modal-content">
                <div class="palmerita-modal-header">
                    <h3 id="palmerita-modal-title"></h3>
                    <button type="button" class="palmerita-modal-close">&times;</button>
                </div>
                <div class="palmerita-modal-body">
                    <p id="palmerita-modal-description"></p>
                    <form id="palmerita-subscription-form">
                        <div class="form-group">
                            <label for="palmerita-email"><?php _e('Email Address:', 'palmerita-subscriptions'); ?></label>
                            <input type="email" id="palmerita-email" name="email" required placeholder="your@email.com">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="palmerita-btn-submit">
                                <span class="btn-text"><?php _e('Subscribe', 'palmerita-subscriptions'); ?></span>
                                <span class="btn-loading" style="display: none;">⏳</span>
                            </button>
                            <button type="button" class="palmerita-btn-cancel"><?php _e('Cancel', 'palmerita-subscriptions'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Process CSV export request for CV or Promo lists.
     * Runs on admin_init so headers can be sent before any output.
     */
    public function maybe_export_csv() {
        if (!is_admin()) {
            return;
        }

        // Ensure we have the expected query vars
        if (empty($_GET['export']) || $_GET['export'] !== 'csv' || empty($_GET['page'])) {
            return;
        }

        $page = sanitize_text_field($_GET['page']);
        if (!in_array($page, ['palmerita-cv-list', 'palmerita-promo-list', 'palmerita-file-list'], true)) {
            return;
        }

        // Determine nonce action and subscription type based on page
        $nonce_action   = $page === 'palmerita-cv-list' ? 'export_cv' : ($page === 'palmerita-file-list' ? 'export_file' : 'export_promo');
        $subscription_type = $page === 'palmerita-cv-list' ? 'cv' : ($page === 'palmerita-file-list' ? 'plugin' : 'promo');

        // Verify nonce
        if (empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], $nonce_action)) {
            wp_die(__('Invalid export request.', 'palmerita-subscriptions'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to export this data.', 'palmerita-subscriptions'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'palmerita_subscriptions';

        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE type = %s AND status = 'active' ORDER BY date_created DESC", $subscription_type));

        // Clean any existing output buffers just in case
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Send headers for CSV download
        $filename_prefix = $subscription_type === 'plugin' ? 'file-downloads' : $subscription_type . '-subscriptions';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename_prefix . '-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Column headers
        fputcsv($output, ['Email', 'Subscription Date', 'IP Address']);

        foreach ($results as $row) {
            fputcsv($output, [
                $row->email,
                $row->date_created,
                $row->ip_address,
            ]);
        }

        fclose($output);
        exit;
    }
    
    /**
     * Configure PHPMailer with custom SMTP credentials (Brevo, Sendgrid, Zoho or any custom host).
     */
    public function configure_phpmailer($phpmailer) {
        $settings = get_option('palmerita_email_settings');

        if (empty($settings) || empty($settings['enabled'])) {
            return; // Use default mailer
        }

        // Force SMTP mode
        $phpmailer->isSMTP();
        $phpmailer->Host       = sanitize_text_field($settings['host'] ?? '');
        $phpmailer->Port       = intval($settings['port'] ?? 587);
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = sanitize_text_field($settings['username'] ?? '');
        $phpmailer->Password   = $settings['password'] ?? '';
        $phpmailer->SMTPSecure = sanitize_text_field($settings['encryption'] ?? 'tls'); // tls/ssl/''

        // Force SSL/TLS settings for common ports
        if ($phpmailer->Port == 465) {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($phpmailer->Port == 587) {
            $phpmailer->SMTPSecure = 'tls';
        }

        // Enable debugging in development/staging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $phpmailer->SMTPDebug = 2; // Enable verbose debug output
            $phpmailer->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: $str");
            };
        }

        // Set timeout values for better reliability
        $phpmailer->Timeout = 30;
        $phpmailer->SMTPTimeout = 30;

        // Disable SSL verification if needed (not recommended for production)
        $allow_self_signed = !empty($settings['smtp_allow_self_signed']);
        $phpmailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => !$allow_self_signed,
                'verify_peer_name' => !$allow_self_signed,
                'allow_self_signed' => $allow_self_signed
            )
        );

        if (!empty($settings['from_email'])) {
            $phpmailer->setFrom($settings['from_email'], $settings['from_name'] ?? '');
        }

        // Log configuration for debugging
        error_log("Palmerita SMTP: Configuring SMTP with host: {$phpmailer->Host}, port: {$phpmailer->Port}, encryption: {$phpmailer->SMTPSecure}, username: {$phpmailer->Username}");
    }
    
    /**
     * Render & handle Email Settings page.
     */
    public function email_settings_page() {
        // Handle save
        if (isset($_POST['palmerita_email_settings_nonce']) && wp_verify_nonce($_POST['palmerita_email_settings_nonce'], 'save_email_settings')) {
            $new_settings = array(
                'enabled'     => isset($_POST['enabled']) ? 1 : 0,
                'provider'    => sanitize_text_field($_POST['provider'] ?? ''),
                'host'        => sanitize_text_field($_POST['host'] ?? ''),
                'port'        => intval($_POST['port'] ?? 587),
                'encryption'  => sanitize_text_field($_POST['encryption'] ?? ''),
                'username'    => sanitize_text_field($_POST['username'] ?? ''),
                'password'    => $_POST['password'] ?? '',
                'from_email'  => sanitize_email($_POST['from_email'] ?? ''),
                'from_name'   => sanitize_text_field($_POST['from_name'] ?? ''),
                'smtp_allow_self_signed' => isset($_POST['smtp_allow_self_signed']) ? 1 : 0,
                'recaptcha_enabled'   => isset($_POST['recaptcha_enabled']) ? 1 : 0,
                'recaptcha_site_key'  => sanitize_text_field($_POST['recaptcha_site_key'] ?? ''),
                'recaptcha_secret'    => sanitize_text_field($_POST['recaptcha_secret'] ?? ''),
                'recaptcha_threshold' => floatval($_POST['recaptcha_threshold'] ?? 0.3),
                'subscriber_help' => sanitize_textarea_field($_POST['subscriber_help'] ?? ''),
            );
            update_option('palmerita_email_settings', $new_settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'palmerita-subscriptions') . '</p></div>';
        }

        $settings = get_option('palmerita_email_settings', array());
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_email = $settings['from_email'] ?? '';
        $from_domain = substr(strrchr($from_email, '@'), 1);
        $domain_warning = '';
        if ($from_email && $from_domain && stripos($site_domain, $from_domain) === false) {
            $domain_warning = '<div class="notice notice-warning"><p><strong>Advertencia:</strong> El correo del remitente no coincide con el dominio del sitio ('.$from_domain.' vs '.$site_domain.'). Esto puede afectar la entregabilidad y causar que los emails lleguen a spam.</p></div>';
        }

        // Simple form UI
        ?>
        <div class="wrap">
            <h1><?php _e('Email / SMTP Settings', 'palmerita-subscriptions'); ?></h1>
            <?php if($domain_warning) echo $domain_warning; ?>
            <form method="post" action="">
                <?php wp_nonce_field('save_email_settings', 'palmerita_email_settings_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enabled"><?php _e('Enable Custom SMTP', 'palmerita-subscriptions'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="enabled" id="enabled" value="1" <?php checked($settings['enabled'] ?? 0, 1); ?> />
                                <p class="description"><?php _e('Tick to route emails via the SMTP credentials below.', 'palmerita-subscriptions'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="provider"><?php _e('Provider', 'palmerita-subscriptions'); ?></label></th>
                            <td>
                                <select name="provider" id="provider">
                                    <?php $providers = array('custom' => 'Custom', 'brevo' => 'Brevo (Sendinblue)', 'sendgrid' => 'SendGrid', 'zoho' => 'Zoho');
                                    foreach ($providers as $slug => $name): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($settings['provider'] ?? '', $slug); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Choosing a provider will pre-fill common host/port values after saving (you can override).', 'palmerita-subscriptions'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="host">SMTP Host</label></th>
                            <td><input type="text" name="host" id="host" class="regular-text" value="<?php echo esc_attr($settings['host'] ?? ''); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="port">Port</label></th>
                            <td><input type="number" name="port" id="port" value="<?php echo esc_attr($settings['port'] ?? 587); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="encryption">Encryption</label></th>
                            <td>
                                <select name="encryption" id="encryption">
                                    <option value="" <?php selected($settings['encryption'] ?? '', ''); ?>>None</option>
                                    <option value="tls" <?php selected($settings['encryption'] ?? '', 'tls'); ?>>TLS</option>
                                    <option value="ssl" <?php selected($settings['encryption'] ?? '', 'ssl'); ?>>SSL</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="username">Username</label></th>
                            <td><input type="text" name="username" id="username" class="regular-text" value="<?php echo esc_attr($settings['username'] ?? ''); ?>" autocomplete="username" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="password">Password / API Key</label></th>
                            <td><input type="password" name="password" id="password" class="regular-text" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" autocomplete="current-password" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_email">From Email</label></th>
                            <td><input type="email" name="from_email" id="from_email" class="regular-text" value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_name">From Name</label></th>
                            <td><input type="text" name="from_name" id="from_name" class="regular-text" value="<?php echo esc_attr($settings['from_name'] ?? ''); ?>" /></td>
                        </tr>

                        <!-- Subscriber Help Message -->
                        <tr>
                            <th scope="row"><label for="subscriber_help"><?php _e('Subscriber Help Message', 'palmerita-subscriptions'); ?></label></th>
                            <td>
                                <textarea name="subscriber_help" id="subscriber_help" class="large-text" rows="3" placeholder="<?php esc_attr_e('e.g. Can\'t find our email? Check your Spam or Promotions folder and move it to Inbox so you never miss our gifts!', 'palmerita-subscriptions'); ?>"><?php echo esc_textarea($settings['subscriber_help'] ?? ''); ?></textarea>
                                <p class="description"><?php _e('This message is appended to all outgoing emails. Use it to ask subscribers to check their Spam/Promotions folder and drag the message to Inbox.', 'palmerita-subscriptions'); ?></p>
                            </td>
                        </tr>

                        <!-- reCAPTCHA settings -->
                        <tr><th><hr/></th><td></td></tr>
                        <tr>
                            <th scope="row"><label for="recaptcha_enabled">Enable reCAPTCHA v3</label></th>
                            <td><input type="checkbox" name="recaptcha_enabled" id="recaptcha_enabled" value="1" <?php checked($settings['recaptcha_enabled'] ?? 0, 1); ?> /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="recaptcha_site_key">Site Key</label></th>
                            <td><input type="text" name="recaptcha_site_key" id="recaptcha_site_key" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="recaptcha_secret">Secret Key</label></th>
                            <td><input type="text" name="recaptcha_secret" id="recaptcha_secret" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_secret'] ?? ''); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="recaptcha_threshold">Score Threshold (0-1)</label></th>
                            <td><input type="number" step="0.1" min="0" max="1" name="recaptcha_threshold" id="recaptcha_threshold" value="<?php echo esc_attr($settings['recaptcha_threshold'] ?? '0.3'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_allow_self_signed">Permitir certificados autofirmados/inseguros</label></th>
                            <td><input type="checkbox" name="smtp_allow_self_signed" id="smtp_allow_self_signed" value="1" <?php checked($settings['smtp_allow_self_signed'] ?? 0, 1); ?> />
                            <p class="description">Solo para pruebas locales. ¡No usar en producción!</p></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr/>
            <h2><?php _e('Send Test Email', 'palmerita-subscriptions'); ?></h2>
            <p><?php _e('Enter an email address below and click "Send Test" to verify your SMTP configuration.', 'palmerita-subscriptions'); ?></p>
            <input type="email" id="palmerita_test_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text" />
            <button class="button button-secondary" id="palmerita_send_test_btn"><?php _e('Send Test', 'palmerita-subscriptions'); ?></button>
            <span id="palmerita_test_result" style="margin-left:1em;"></span>
        </div>

        <div class="notice notice-info" style="margin-top:20px;">
            <p><strong><?php _e('Anti-Spam Best Practices', 'palmerita-subscriptions'); ?></strong></p>
            <ul style="list-style:disc;padding-left:20px;">
                <li><?php _e('Use a personal, conversational subject line – avoid words like "Download", "Free", "Urgent".', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Keep the body short, friendly and human – minimise links and commercial jargon.', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Make sure your "From Email" matches the authenticated SMTP domain.', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Ask subscribers to check their Spam/Promotions folder and move the message to Inbox.', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Authenticate your domain with SPF, DKIM and DMARC for best deliverability.', 'palmerita-subscriptions'); ?></li>
            </ul>
        </div>

        <script>
        (function($){
            // Provider auto-fill defaults
            const defaults = {
                brevo:  {host:'smtp-relay.brevo.com', port:587, enc:'tls'},
                sendgrid:{host:'smtp.sendgrid.net',  port:587, enc:'tls'},
                zoho:   {host:'smtp.zoho.com',       port:465, enc:'ssl'}
            };

            $('#provider').on('change', function(){
                const sel = $(this).val();
                if(defaults[sel]){
                    $('#host').val(defaults[sel].host);
                    $('#port').val(defaults[sel].port);
                    $('#encryption').val(defaults[sel].enc);
                }
            });

            // Send test email
            $('#palmerita_send_test_btn').on('click', function(e){
                e.preventDefault();
                const email = $('#palmerita_test_email').val();
                $('#palmerita_test_result').text('<?php echo esc_js(__('Sending…','palmerita-subscriptions')); ?>');
                $.post(ajaxurl, {
                    action:'palmerita_send_test_email',
                    nonce: '<?php echo wp_create_nonce('palmerita_send_test_email'); ?>',
                    test_email: email
                }, function(resp){
                    if(resp.success){
                        $('#palmerita_test_result').css('color','green').text(resp.data);
                    } else {
                        $('#palmerita_test_result').css('color','red').text(resp.data);
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * AJAX handler to send a test email using current SMTP settings.
     */
    public function send_test_email() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'palmerita-subscriptions'));
        }

        check_ajax_referer('palmerita_send_test_email', 'nonce');

        $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : get_option('admin_email');
        if (!is_email($to)) {
            wp_send_json_error(__('Invalid email address', 'palmerita-subscriptions'));
        }

        // Capture PHPMailer errors
        add_action('wp_mail_failed', array($this, 'log_wp_mail_error'));

        $subject = __('Palmerita Subscriptions – Test Email', 'palmerita-subscriptions');
        $message = __('This is a test email confirming your SMTP settings are working.', 'palmerita-subscriptions');
        
        // Add extra headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($to, $subject, $message, $headers);

        remove_action('wp_mail_failed', array($this, 'log_wp_mail_error'));

        $settings = get_option('palmerita_email_settings', array());
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_email = $settings['from_email'] ?? '';
        $from_domain = substr(strrchr($from_email, '@'), 1);
        $domain_warning = '';
        if ($from_email && $from_domain && stripos($site_domain, $from_domain) === false) {
            $domain_warning = ' <strong>Advertencia:</strong> El correo del remitente no coincide con el dominio del sitio ('.$from_domain.' vs '.$site_domain.'). Esto puede afectar la entregabilidad y causar que los emails lleguen a spam.';
        }

        if ($sent) {
            wp_send_json_success(__('Test email sent successfully. Please check your inbox.', 'palmerita-subscriptions') . $domain_warning);
        } else {
            $settings = get_option('palmerita_email_settings', array());
            $error_msg = __('Failed to send test email. Check your SMTP settings.', 'palmerita-subscriptions');
            
            // Add debugging info for admins
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_info = array(
                    'Host: ' . ($settings['host'] ?? 'Not set'),
                    'Port: ' . ($settings['port'] ?? 'Not set'),
                    'Username: ' . ($settings['username'] ?? 'Not set'),
                    'Encryption: ' . ($settings['encryption'] ?? 'Not set'),
                    'From Email: ' . ($settings['from_email'] ?? 'Not set')
                );
                $error_msg .= ' Debug info: ' . implode(', ', $debug_info);
            }
            
            wp_send_json_error($error_msg . $domain_warning);
        }
    }

    /**
     * Log wp_mail errors for debugging
     */
    public function log_wp_mail_error($wp_error) {
        $error_msg = 'Palmerita WP Mail Error: ' . $wp_error->get_error_message();
        
        // Add additional debug info
        $settings = get_option('palmerita_email_settings', array());
        if (!empty($settings['enabled'])) {
            $error_msg .= ' | SMTP Config: host=' . ($settings['host'] ?? 'none') . 
                         ', port=' . ($settings['port'] ?? 'none') . 
                         ', encryption=' . ($settings['encryption'] ?? 'none') . 
                         ', from_email=' . ($settings['from_email'] ?? 'none');
        } else {
            $error_msg .= ' | Using default WordPress mail (SMTP not configured)';
        }
        
        error_log($error_msg);
    }

    /**
     * Check if reCAPTCHA v3 is enabled and configured.
     */
    private function recaptcha_enabled() {
        $set = get_option('palmerita_email_settings', array());
        return !empty($set['recaptcha_enabled']) && !empty($set['recaptcha_site_key']) && !empty($set['recaptcha_secret']);
    }

    // Shortcode render_plugin_button
    public function render_plugin_button($atts){
        $atts = shortcode_atts(array(
            'style'      => 'gradient',        // internal style variant (gradient, solid…)
            'text'       => '',               // override button label
            'class'      => '',               // extra CSS classes passed from theme
            'no_default' => false,            // when true, omit plugin default styling classes
        ), $atts, 'palmerita_file_button');

        $copy = get_option('palmerita_modal_copy', array());
        $btn_text  = !empty($copy['file_btn_text']) ? $copy['file_btn_text'] : ( $atts['text'] ?: __('Download File','palmerita-subscriptions') );
        $btn_icon  = isset($copy['file_btn_icon'])  ? $copy['file_btn_icon']  : '🛠️';
        $btn_color = !empty($copy['file_btn_color'])? $copy['file_btn_color']: '';
        $btn_text_color = !empty($copy['file_text_color']) ? $copy['file_text_color'] : '';
        $style_attr  = ($btn_color || $btn_text_color) ? 'style="'.($btn_color ? 'background-color:'.esc_attr($btn_color).'; background-image: none; ' : '').($btn_text_color ? 'color:'.esc_attr($btn_text_color).' !important;' : '').'"' : '';
        $style_class = 'palmerita-btn--' . esc_attr($atts['style']);
        $extra_class = sanitize_text_field( $atts['class'] );
        ob_start();
        ?>
        <?php
        $base_classes = '';
        if( !$atts['no_default'] ){
            $base_classes = 'palmerita-btn palmerita-btn-plugin ' . $style_class;
        }
        ?>
        <button type="button" class="<?php echo $base_classes . ' ' . esc_attr( $extra_class ); ?>" data-type="plugin" data-palmerita-btn="1" <?php echo $style_attr; ?> >
            <?php if($btn_icon){ ?><span class="btn-icon"><?php echo esc_html($btn_icon); ?></span><?php } ?>
            <span class="btn-text"><?php echo esc_html($btn_text); ?></span>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Modal Copy admin page – customise titles & descriptions.
     */
    public function modal_copy_page(){
        // Handle save
        if(isset($_POST['pal_modal_copy_nonce']) && wp_verify_nonce($_POST['pal_modal_copy_nonce'],'save_modal_copy')){
            $copy = array(
                // CV
                'cv_title'   => sanitize_text_field( wp_unslash($_POST['cv_title'] ?? '') ),
                'cv_desc'    => sanitize_textarea_field( wp_unslash($_POST['cv_desc'] ?? '') ),
                'cv_btn_text'=> sanitize_text_field( wp_unslash($_POST['cv_btn_text'] ?? '') ),
                'cv_btn_icon'=> sanitize_text_field( wp_unslash($_POST['cv_btn_icon'] ?? '') ),
                'cv_btn_color'=> sanitize_hex_color( wp_unslash($_POST['cv_btn_color'] ?? '') ),
                'cv_text_color'=> sanitize_hex_color( wp_unslash($_POST['cv_text_color'] ?? '') ),
                'cv_submit_text' => sanitize_text_field( wp_unslash($_POST['cv_submit_text'] ?? '') ),

                // Promo
                'promo_title'=> sanitize_text_field( wp_unslash($_POST['promo_title'] ?? '') ),
                'promo_desc' => sanitize_textarea_field( wp_unslash($_POST['promo_desc'] ?? '') ),
                'promo_btn_text'=> sanitize_text_field( wp_unslash($_POST['promo_btn_text'] ?? '') ),
                'promo_btn_icon'=> sanitize_text_field( wp_unslash($_POST['promo_btn_icon'] ?? '') ),
                'promo_btn_color'=> sanitize_hex_color( wp_unslash($_POST['promo_btn_color'] ?? '') ),
                'promo_text_color'=> sanitize_hex_color( wp_unslash($_POST['promo_text_color'] ?? '') ),
                'promo_submit_text' => sanitize_text_field( wp_unslash($_POST['promo_submit_text'] ?? '') ),

                // File
                'file_title' => sanitize_text_field( wp_unslash($_POST['file_title'] ?? '') ),
                'file_desc'  => sanitize_textarea_field( wp_unslash($_POST['file_desc'] ?? '') ),
                'file_btn_text'=> sanitize_text_field( wp_unslash($_POST['file_btn_text'] ?? '') ),
                'file_btn_icon'=> sanitize_text_field( wp_unslash($_POST['file_btn_icon'] ?? '') ),
                'file_btn_color'=> sanitize_hex_color( wp_unslash($_POST['file_btn_color'] ?? '') ),
                'file_text_color'=> sanitize_hex_color( wp_unslash($_POST['file_text_color'] ?? '') ),
                'file_submit_text' => sanitize_text_field( wp_unslash($_POST['file_submit_text'] ?? '') ),
            );
            update_option('palmerita_modal_copy',$copy);
            echo '<div class="notice notice-success is-dismissible"><p>'.__('Modal copy saved.','palmerita-subscriptions').'</p></div>';
        }

        $copy=get_option('palmerita_modal_copy', array());
        ?>
        <div class="wrap">
            <h1><?php _e('Modal Copy Settings','palmerita-subscriptions'); ?></h1>
            <p class="description"><?php _e('Customize button appearance, modal content, and see real-time previews of your changes.','palmerita-subscriptions'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_modal_copy','pal_modal_copy_nonce'); ?>
                
                <!-- CV Button Section -->
                <div class="palmerita-section">
                    <h2><?php _e('CV Button','palmerita-subscriptions'); ?></h2>
                    <p class="description"><?php _e('Configure the button that users click to request your CV. This controls both the button appearance and the modal content.','palmerita-subscriptions'); ?></p>
                    <div class="palmerita-admin-grid">
                        <div class="palmerita-form-column">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th><label for="cv_title"><?php _e('Modal Title','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="cv_title" id="cv_title" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['cv_title'] ?? '') ); ?>" placeholder="<?php _e('Get my CV','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Title shown in the popup when users click the button','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cv_desc"><?php _e('Modal Description','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <textarea name="cv_desc" id="cv_desc" class="large-text" rows="3" placeholder="<?php _e('Enter modal description...','palmerita-subscriptions'); ?>"><?php echo esc_textarea( wp_unslash($copy['cv_desc'] ?? '') ); ?></textarea>
                                            <p class="description"><?php _e('Description text shown in the popup','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cv_btn_text"><?php _e('Button Text','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="cv_btn_text" id="cv_btn_text" class="regular-text btn-text-input" data-target="cv-preview" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_text'] ?? '') ); ?>" placeholder="<?php _e('Get my CV','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Text displayed on the button','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cv_btn_icon"><?php _e('Button Icon','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="cv_btn_icon" id="cv_btn_icon" class="regular-text btn-icon-input" data-target="cv-preview" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_icon'] ?? '') ); ?>" placeholder="📄" />
                                            <p class="description"><?php _e('Use emoji or HTML. Leave empty for default SVG icon.','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cv_btn_color"><?php _e('Background Color','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <div class="palmerita-color-group">
                                                <input type="color" name="cv_btn_color" id="cv_btn_color" class="btn-bg-color" data-target="cv-preview" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_color'] ?? '#6366f1') ); ?>" />
                                                <input type="text" class="color-hex-input" data-color-input="cv_btn_color" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_color'] ?? '#6366f1') ); ?>" placeholder="#6366f1" />
                                                <button type="button" class="button color-reset" data-target="cv_btn_color" data-default="#6366f1"><?php _e('Reset','palmerita-subscriptions'); ?></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cv_text_color"><?php _e('Text Color','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <div class="palmerita-color-group">
                                                <input type="color" name="cv_text_color" id="cv_text_color" class="btn-text-color" data-target="cv-preview" value="<?php echo esc_attr( wp_unslash($copy['cv_text_color'] ?? '#ffffff') ); ?>" />
                                                <input type="text" class="color-hex-input" data-color-input="cv_text_color" value="<?php echo esc_attr( wp_unslash($copy['cv_text_color'] ?? '#ffffff') ); ?>" placeholder="#ffffff" />
                                                <button type="button" class="button color-reset" data-target="cv_text_color" data-default="#ffffff"><?php _e('Reset','palmerita-subscriptions'); ?></button>
                                            </div>
                                            <div class="contrast-indicator" id="cv-contrast"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cv_submit_text"><?php _e('Submit Button Text','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="cv_submit_text" id="cv_submit_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['cv_submit_text'] ?? '') ); ?>" placeholder="<?php _e('Send CV Link','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Text for the submit button inside the modal','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="palmerita-preview-column">
                            <div class="palmerita-preview-container">
                                <h3><?php _e('Live Preview','palmerita-subscriptions'); ?></h3>
                                <div class="palmerita-preview-button-container">
                                    <button type="button" id="cv-preview" class="palmerita-btn palmerita-btn-cv" style="background-color: <?php echo esc_attr( wp_unslash($copy['cv_btn_color'] ?? '#6366f1') ); ?>; background-image: none; color: <?php echo esc_attr( wp_unslash($copy['cv_text_color'] ?? '#ffffff') ); ?>;">
                                        <span class="btn-icon"><?php echo esc_html( wp_unslash($copy['cv_btn_icon'] ?? '📄') ); ?></span>
                                        <span class="btn-text"><?php echo esc_html( wp_unslash($copy['cv_btn_text'] ?? 'Get my CV') ); ?></span>
                                    </button>
                                </div>
                                <div class="palmerita-preview-tips">
                                    <h4><?php _e('Design Tips','palmerita-subscriptions'); ?></h4>
                                    <ul>
                                        <li><?php _e('Ensure good contrast between background and text colors','palmerita-subscriptions'); ?></li>
                                        <li><?php _e('Test on different devices and screen sizes','palmerita-subscriptions'); ?></li>
                                        <li><?php _e('Keep text concise and action-oriented','palmerita-subscriptions'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Promo Button Section -->
                <div class="palmerita-section">
                    <h2><?php _e('Promotions Button','palmerita-subscriptions'); ?></h2>
                    <p class="description"><?php _e('Configure the button for promotional subscriptions. Users will subscribe to receive special offers and updates.','palmerita-subscriptions'); ?></p>
                    <div class="palmerita-admin-grid">
                        <div class="palmerita-form-column">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th><label for="promo_title"><?php _e('Modal Title','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="promo_title" id="promo_title" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['promo_title'] ?? '') ); ?>" placeholder="<?php _e('Get Special Offers','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Title shown in the popup when users click the button','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="promo_desc"><?php _e('Modal Description','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <textarea name="promo_desc" id="promo_desc" class="large-text" rows="3" placeholder="<?php _e('Enter modal description...','palmerita-subscriptions'); ?>"><?php echo esc_textarea( wp_unslash($copy['promo_desc'] ?? '') ); ?></textarea>
                                            <p class="description"><?php _e('Description text shown in the popup','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="promo_btn_text"><?php _e('Button Text','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="promo_btn_text" id="promo_btn_text" class="regular-text btn-text-input" data-target="promo-preview" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_text'] ?? '') ); ?>" placeholder="<?php _e('Get Offers','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Text displayed on the button','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="promo_btn_icon"><?php _e('Button Icon','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="promo_btn_icon" id="promo_btn_icon" class="regular-text btn-icon-input" data-target="promo-preview" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_icon'] ?? '') ); ?>" placeholder="🎯" />
                                            <p class="description"><?php _e('Use emoji or HTML. Leave empty for default SVG icon.','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="promo_btn_color"><?php _e('Background Color','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <div class="palmerita-color-group">
                                                <input type="color" name="promo_btn_color" id="promo_btn_color" class="btn-bg-color" data-target="promo-preview" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_color'] ?? '#f59e0b') ); ?>" />
                                                <input type="text" class="color-hex-input" data-color-input="promo_btn_color" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_color'] ?? '#f59e0b') ); ?>" placeholder="#f59e0b" />
                                                <button type="button" class="button color-reset" data-target="promo_btn_color" data-default="#f59e0b"><?php _e('Reset','palmerita-subscriptions'); ?></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="promo_text_color"><?php _e('Text Color','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <div class="palmerita-color-group">
                                                <input type="color" name="promo_text_color" id="promo_text_color" class="btn-text-color" data-target="promo-preview" value="<?php echo esc_attr( wp_unslash($copy['promo_text_color'] ?? '#ffffff') ); ?>" />
                                                <input type="text" class="color-hex-input" data-color-input="promo_text_color" value="<?php echo esc_attr( wp_unslash($copy['promo_text_color'] ?? '#ffffff') ); ?>" placeholder="#ffffff" />
                                                <button type="button" class="button color-reset" data-target="promo_text_color" data-default="#ffffff"><?php _e('Reset','palmerita-subscriptions'); ?></button>
                                            </div>
                                            <div class="contrast-indicator" id="promo-contrast"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="promo_submit_text"><?php _e('Submit Button Text','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="promo_submit_text" id="promo_submit_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['promo_submit_text'] ?? '') ); ?>" placeholder="<?php _e('Subscribe Now','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Text for the submit button inside the modal','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="palmerita-preview-column">
                            <div class="palmerita-preview-container">
                                <h3><?php _e('Live Preview','palmerita-subscriptions'); ?></h3>
                                <div class="palmerita-preview-button-container">
                                    <button type="button" id="promo-preview" class="palmerita-btn palmerita-btn-promo" style="background-color: <?php echo esc_attr( wp_unslash($copy['promo_btn_color'] ?? '#f59e0b') ); ?>; background-image: none; color: <?php echo esc_attr( wp_unslash($copy['promo_text_color'] ?? '#ffffff') ); ?>;">
                                        <span class="btn-icon"><?php echo esc_html( wp_unslash($copy['promo_btn_icon'] ?? '🎯') ); ?></span>
                                        <span class="btn-text"><?php echo esc_html( wp_unslash($copy['promo_btn_text'] ?? 'Get Offers') ); ?></span>
                                    </button>
                                </div>
                                <div class="palmerita-preview-tips">
                                    <h4><?php _e('Design Tips','palmerita-subscriptions'); ?></h4>
                                    <ul>
                                        <li><?php _e('Use attention-grabbing colors for promotional content','palmerita-subscriptions'); ?></li>
                                        <li><?php _e('Make the call-to-action clear and compelling','palmerita-subscriptions'); ?></li>
                                        <li><?php _e('Consider seasonal or brand-specific colors','palmerita-subscriptions'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Button Section -->
                <div class="palmerita-section">
                    <h2><?php _e('File Download Button','palmerita-subscriptions'); ?></h2>
                    <p class="description"><?php _e('Configure the button for file downloads. Users will receive download links for your files or plugins.','palmerita-subscriptions'); ?></p>
                    <div class="palmerita-admin-grid">
                        <div class="palmerita-form-column">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th><label for="file_title"><?php _e('Modal Title','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="file_title" id="file_title" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['file_title'] ?? '') ); ?>" placeholder="<?php _e('Download File','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Title shown in the popup when users click the button','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_desc"><?php _e('Modal Description','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <textarea name="file_desc" id="file_desc" class="large-text" rows="3" placeholder="<?php _e('Enter modal description...','palmerita-subscriptions'); ?>"><?php echo esc_textarea( wp_unslash($copy['file_desc'] ?? '') ); ?></textarea>
                                            <p class="description"><?php _e('Description text shown in the popup','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_btn_text"><?php _e('Button Text','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="file_btn_text" id="file_btn_text" class="regular-text btn-text-input" data-target="file-preview" value="<?php echo esc_attr( wp_unslash($copy['file_btn_text'] ?? '') ); ?>" placeholder="<?php _e('Download File','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Text displayed on the button','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_btn_icon"><?php _e('Button Icon','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="file_btn_icon" id="file_btn_icon" class="regular-text btn-icon-input" data-target="file-preview" value="<?php echo esc_attr( wp_unslash($copy['file_btn_icon'] ?? '') ); ?>" placeholder="🛠️" />
                                            <p class="description"><?php _e('Use emoji or HTML. Leave empty for default SVG icon.','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_btn_color"><?php _e('Background Color','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <div class="palmerita-color-group">
                                                <input type="color" name="file_btn_color" id="file_btn_color" class="btn-bg-color" data-target="file-preview" value="<?php echo esc_attr( wp_unslash($copy['file_btn_color'] ?? '#10b981') ); ?>" />
                                                <input type="text" class="color-hex-input" data-color-input="file_btn_color" value="<?php echo esc_attr( wp_unslash($copy['file_btn_color'] ?? '#10b981') ); ?>" placeholder="#10b981" />
                                                <button type="button" class="button color-reset" data-target="file_btn_color" data-default="#10b981"><?php _e('Reset','palmerita-subscriptions'); ?></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_text_color"><?php _e('Text Color','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <div class="palmerita-color-group">
                                                <input type="color" name="file_text_color" id="file_text_color" class="btn-text-color" data-target="file-preview" value="<?php echo esc_attr( wp_unslash($copy['file_text_color'] ?? '#ffffff') ); ?>" />
                                                <input type="text" class="color-hex-input" data-color-input="file_text_color" value="<?php echo esc_attr( wp_unslash($copy['file_text_color'] ?? '#ffffff') ); ?>" placeholder="#ffffff" />
                                                <button type="button" class="button color-reset" data-target="file_text_color" data-default="#ffffff"><?php _e('Reset','palmerita-subscriptions'); ?></button>
                                            </div>
                                            <div class="contrast-indicator" id="file-contrast"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_submit_text"><?php _e('Submit Button Text','palmerita-subscriptions'); ?></label></th>
                                        <td>
                                            <input type="text" name="file_submit_text" id="file_submit_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['file_submit_text'] ?? '') ); ?>" placeholder="<?php _e('Get Download Link','palmerita-subscriptions'); ?>" />
                                            <p class="description"><?php _e('Text for the submit button inside the modal','palmerita-subscriptions'); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="palmerita-preview-column">
                            <div class="palmerita-preview-container">
                                <h3><?php _e('Live Preview','palmerita-subscriptions'); ?></h3>
                                <div class="palmerita-preview-button-container">
                                    <button type="button" id="file-preview" class="palmerita-btn palmerita-btn-file" style="background-color: <?php echo esc_attr( wp_unslash($copy['file_btn_color'] ?? '#10b981') ); ?>; background-image: none; color: <?php echo esc_attr( wp_unslash($copy['file_text_color'] ?? '#ffffff') ); ?>;">
                                        <span class="btn-icon"><?php echo esc_html( wp_unslash($copy['file_btn_icon'] ?? '🛠️') ); ?></span>
                                        <span class="btn-text"><?php echo esc_html( wp_unslash($copy['file_btn_text'] ?? 'Download File') ); ?></span>
                                    </button>
                                </div>
                                <div class="palmerita-preview-tips">
                                    <h4><?php _e('Design Tips','palmerita-subscriptions'); ?></h4>
                                    <ul>
                                        <li><?php _e('Use professional colors that inspire trust','palmerita-subscriptions'); ?></li>
                                        <li><?php _e('Make the download purpose clear in the button text','palmerita-subscriptions'); ?></li>
                                        <li><?php _e('Consider using icons that represent the file type','palmerita-subscriptions'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save Changes','palmerita-subscriptions'), 'primary', 'submit', true, array('class' => 'palmerita-save-btn')); ?>
            </form>
        </div>

        <style>
        .palmerita-section {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .palmerita-section h2 {
            color: #1d2327;
            font-size: 18px;
            margin: 0 0 8px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f1;
        }
        
        .palmerita-section .description {
            margin-bottom: 20px;
            color: #646970;
        }
        
        .palmerita-admin-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            align-items: start;
        }
        
        .palmerita-form-column .form-table th {
            width: 180px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .palmerita-color-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .palmerita-color-group input[type="color"] {
            width: 50px;
            height: 40px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            padding: 0;
        }
        
        .color-hex-input {
            width: 100px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        
        .color-reset {
            font-size: 12px;
            padding: 6px 12px;
        }
        
        .palmerita-preview-container {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            position: sticky;
            top: 32px;
        }
        
        .palmerita-preview-container h3 {
            margin: 0 0 16px 0;
            color: #1d2327;
            font-size: 16px;
        }
        
        .palmerita-preview-button-container {
            display: flex;
            justify-content: center;
            padding: 20px;
            background: #fff;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 2px dashed #ddd;
        }
        
        .palmerita-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .palmerita-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .palmerita-btn .btn-icon {
            font-size: 16px;
        }
        
        .palmerita-btn .btn-text {
            font-size: 14px;
        }
        
        .palmerita-preview-tips {
            background: #fff;
            padding: 16px;
            border-radius: 6px;
            border-left: 4px solid #2271b1;
        }
        
        .palmerita-preview-tips h4 {
            margin: 0 0 12px 0;
            color: #1d2327;
            font-size: 14px;
        }
        
        .palmerita-preview-tips ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .palmerita-preview-tips li {
            font-size: 13px;
            color: #646970;
            margin-bottom: 6px;
        }
        
        .contrast-indicator {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .contrast-good {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        
        .contrast-poor {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c2c7;
        }
        
        .palmerita-save-btn {
            background: linear-gradient(135deg, #2271b1, #135e96) !important;
            border-color: #135e96 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            font-size: 14px !important;
            padding: 12px 24px !important;
            height: auto !important;
        }
        
        @media (max-width: 1200px) {
            .palmerita-admin-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .palmerita-preview-container {
                position: static;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Función para calcular contraste
            function calculateContrast(bg, text) {
                function hexToRgb(hex) {
                    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                    return result ? {
                        r: parseInt(result[1], 16),
                        g: parseInt(result[2], 16),
                        b: parseInt(result[3], 16)
                    } : null;
                }
                
                function luminance(r, g, b) {
                    const [rs, gs, bs] = [r, g, b].map(c => {
                        c = c / 255;
                        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
                    });
                    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
                }
                
                const bgRgb = hexToRgb(bg);
                const textRgb = hexToRgb(text);
                
                if (!bgRgb || !textRgb) return 1;
                
                const bgLum = luminance(bgRgb.r, bgRgb.g, bgRgb.b);
                const textLum = luminance(textRgb.r, textRgb.g, textRgb.b);
                
                const brightest = Math.max(bgLum, textLum);
                const darkest = Math.min(bgLum, textLum);
                
                return (brightest + 0.05) / (darkest + 0.05);
            }
            
            // Función para actualizar indicador de contraste
            function updateContrastIndicator(bgColor, textColor, targetId) {
                const contrast = calculateContrast(bgColor, textColor);
                const indicator = $('#' + targetId + '-contrast');
                
                indicator.removeClass('contrast-good contrast-poor');
                
                if (contrast >= 4.5) {
                    indicator.addClass('contrast-good');
                    indicator.text('✓ Buen contraste (WCAG AA: ' + contrast.toFixed(1) + ':1)');
                } else {
                    indicator.addClass('contrast-poor');
                    indicator.text('⚠ Contraste bajo (WCAG AA: ' + contrast.toFixed(1) + ':1)');
                }
            }
            
            // Función para actualizar vista previa
            function updatePreview(target, property, value) {
                const preview = $('#' + target);
                if (property === 'background-color') {
                    preview.css({
                        'background-color': value,
                        'background-image': 'none'
                    });
                    const textColor = preview.css('color');
                    updateContrastIndicator(value, textColor, target.replace('-preview', ''));
                } else if (property === 'color') {
                    preview.css('color', value);
                    const bgColor = preview.css('background-color');
                    // Convertir rgb a hex para el cálculo
                    const rgb = bgColor.match(/\d+/g);
                    if (rgb) {
                        const hex = '#' + rgb.map(x => (+x).toString(16).padStart(2, '0')).join('');
                        updateContrastIndicator(hex, value, target.replace('-preview', ''));
                    }
                }
            }
            
            // Event listeners para colores
            $('.btn-bg-color').on('input change', function() {
                const target = $(this).data('target');
                const color = $(this).val();
                updatePreview(target, 'background-color', color);
                
                // Sincronizar con input de texto
                $('[data-color-input="' + $(this).attr('id') + '"]').val(color);
            });
            
            $('.btn-text-color').on('input change', function() {
                const target = $(this).data('target');
                const color = $(this).val();
                updatePreview(target, 'color', color);
                
                // Sincronizar con input de texto
                $('[data-color-input="' + $(this).attr('id') + '"]').val(color);
            });
            
            // Event listeners para inputs de texto
            $('.btn-text-input').on('input', function() {
                const target = $(this).data('target');
                const text = $(this).val() || $(this).attr('placeholder');
                $('#' + target).find('.btn-text').text(text);
            });
            
            $('.btn-icon-input').on('input', function() {
                const target = $(this).data('target');
                const icon = $(this).val();
                $('#' + target).find('.btn-icon').html(icon);
            });
            
            // Event listeners para inputs hex
            $('.color-hex-input').on('input', function() {
                const targetId = $(this).data('color-input');
                const color = $(this).val();
                if (/^#[0-9A-F]{6}$/i.test(color)) {
                    $('#' + targetId).val(color).trigger('change');
                }
            });
            
            // Event listeners para botones reset
            $('.color-reset').on('click', function() {
                const targetId = $(this).data('target');
                const defaultColor = $(this).data('default');
                $('#' + targetId).val(defaultColor).trigger('change');
                $('[data-color-input="' + targetId + '"]').val(defaultColor);
            });
            
            // Inicializar indicadores de contraste para todos los botones
            function initializeContrastIndicators() {
                const buttons = ['cv', 'promo', 'file'];
                
                buttons.forEach(function(type) {
                    const preview = $('#' + type + '-preview');
                    if (preview.length) {
                        const bgColor = preview.css('background-color');
                        const textColor = preview.css('color');
                        
                        // Convertir rgb a hex
                        const bgRgb = bgColor.match(/\d+/g);
                        const textRgb = textColor.match(/\d+/g);
                        
                        if (bgRgb && textRgb) {
                            const bgHex = '#' + bgRgb.map(x => (+x).toString(16).padStart(2, '0')).join('');
                            const textHex = '#' + textRgb.map(x => (+x).toString(16).padStart(2, '0')).join('');
                            updateContrastIndicator(bgHex, textHex, type);
                        }
                    }
                });
            }
            
            // Inicializar al cargar la página
            initializeContrastIndicators();
        });
        </script>
        <?php
    }

    /**
     * Render Email Templates page
     */
    public function email_templates_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'palmerita-subscriptions'));
        }

        // Limpieza una sola vez de plantillas existentes
        $templates_cleaned = get_option('palmerita_templates_cleaned', false);
        if (!$templates_cleaned) {
            $this->clean_existing_templates();
            update_option('palmerita_templates_cleaned', true);
        }
        
        // Forzar restauración completa de plantillas (forzar cada vez para debug)
        $templates_restored = get_option('palmerita_templates_css_restored', false);
        if (!$templates_restored) {
            $this->force_restore_templates_with_css();
            update_option('palmerita_templates_css_restored', true);
        }

        // handle save
        if (isset($_POST['palmerita_templates_nonce']) && wp_verify_nonce($_POST['palmerita_templates_nonce'],'save_email_templates')) {
            $templates = array(
                'cv'     => $this->clean_template_content($_POST['template_cv'] ?? ''),
                'file'   => $this->clean_template_content($_POST['template_file'] ?? ''),
                'promo'  => $this->clean_template_content($_POST['template_promo'] ?? ''),
            );
            update_option('palmerita_email_templates', $templates);
            echo '<div class="notice notice-success is-dismissible animated fadeIn"><p>'.__('Templates saved.','palmerita-subscriptions').'</p></div>';
        }

        $templates = get_option('palmerita_email_templates', array('cv'=>'','file'=>'','promo'=>''));

        // Limpiar slashes innecesarios automáticamente
        foreach ($templates as $key => $template) {
            if (!empty($template)) {
                // Eliminar slashes dobles o múltiples
                $templates[$key] = stripslashes($template);
                // Si aún hay slashes múltiples, repetir
                while (strpos($templates[$key], '\\\\') !== false) {
                    $templates[$key] = stripslashes($templates[$key]);
                }
            }
        }

        // Pre-populate with default templates if empty so the user can edit instead of starting from scratch
        if(empty($templates['cv']) && method_exists('PalmeritaDownloadManager','get_download_email_template')){
            $templates['cv'] = PalmeritaDownloadManager::get_download_email_template('{{download_url}}');
        }
        if(empty($templates['file']) && method_exists('PalmeritaDownloadManager','get_zip_email_template')){
            $templates['file'] = PalmeritaDownloadManager::get_zip_email_template('{{download_url}}');
        }
        if(empty($templates['promo']) && method_exists('PalmeritaDownloadManager','get_promo_welcome_email_template')){
            $templates['promo'] = PalmeritaDownloadManager::get_promo_welcome_email_template();
        }

        // Iconos SVG para tabs
        $icons = array(
            'cv' => '<svg width="20" height="20" fill="none" viewBox="0 0 20 20"><rect x="3" y="3" width="14" height="14" rx="2" fill="#0073aa"/><rect x="6" y="6" width="8" height="2" rx="1" fill="#fff"/><rect x="6" y="10" width="8" height="2" rx="1" fill="#fff"/></svg>',
            'file' => '<svg width="20" height="20" fill="none" viewBox="0 0 20 20"><rect x="4" y="4" width="12" height="12" rx="2" fill="#0073aa"/><path d="M8 8h4v4H8z" fill="#fff"/></svg>',
            'promo' => '<svg width="20" height="20" fill="none" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="#0073aa"/><path d="M10 6v4l3 2" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
        );
        ?>
        <div class="wrap palmerita-email-templates">
            <h1 style="display:flex;align-items:center;gap:10px;"><span>📧</span> <?php _e('Email Templates', 'palmerita-subscriptions'); ?></h1>
            <div class="palmerita-instructions" style="background:#f8fafc;border:1px solid #ccd0d4;padding:16px;border-radius:6px;margin-bottom:18px;max-width:900px;">
                <h2 style="margin-top:0;font-size:1.2em;">✍️ <?php _e('How to customize your emails','palmerita-subscriptions'); ?></h2>
                <ul style="margin-left:20px;">
                    <li><?php _e('Edit the HTML for each email below.','palmerita-subscriptions'); ?></li>
                    <li><?php _e('You can use these tokens:','palmerita-subscriptions'); ?> <code>{{download_url}}</code>, <code>{{subscriber_help}}</code></li>
                    <li><?php _e('Click "Restore Default" to reset a template to its original content.','palmerita-subscriptions'); ?></li>
                    <li><?php _e('Preview updates live as you edit.','palmerita-subscriptions'); ?></li>
                </ul>
            </div>
            <form method="post" action="" id="palmerita_templates_form">
                <?php wp_nonce_field('save_email_templates','palmerita_templates_nonce'); ?>
                <nav class="nav-tab-wrapper palmerita-big-tabs" id="palmerita-template-tabs" style="margin-bottom:0;">
                    <a href="#template_cv" class="nav-tab nav-tab-active" style="font-size:1.1em;display:flex;align-items:center;gap:6px;"><?php echo $icons['cv']; ?> <?php _e('CV Email','palmerita-subscriptions'); ?></a>
                    <a href="#template_file" class="nav-tab" style="font-size:1.1em;display:flex;align-items:center;gap:6px;"><?php echo $icons['file']; ?> <?php _e('File Email','palmerita-subscriptions'); ?></a>
                    <a href="#template_promo" class="nav-tab" style="font-size:1.1em;display:flex;align-items:center;gap:6px;"><?php echo $icons['promo']; ?> <?php _e('Promo Welcome','palmerita-subscriptions'); ?></a>
                </nav>
                <div id="palmerita-template-flex">
                    <div id="template_panels">
                        <div class="palmerita-template-panel" data-type="cv" style="display:block;">
                            <textarea id="template_cv" name="template_cv" class="palmerita-template"><?php echo htmlspecialchars($templates['cv'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <button type="button" class="button palmerita-restore-btn" data-type="cv" style="margin-top:6px;"><span>🔄</span> <?php _e('Restore Default','palmerita-subscriptions'); ?></button>
                        </div>
                        <div class="palmerita-template-panel" data-type="file" style="display:none;">
                            <textarea id="template_file" name="template_file" class="palmerita-template"><?php echo htmlspecialchars($templates['file'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <button type="button" class="button palmerita-restore-btn" data-type="file" style="margin-top:6px;"><span>🔄</span> <?php _e('Restore Default','palmerita-subscriptions'); ?></button>
                        </div>
                        <div class="palmerita-template-panel" data-type="promo" style="display:none;">
                            <textarea id="template_promo" name="template_promo" class="palmerita-template"><?php echo htmlspecialchars($templates['promo'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <button type="button" class="button palmerita-restore-btn" data-type="promo" style="margin-top:6px;"><span>🔄</span> <?php _e('Restore Default','palmerita-subscriptions'); ?></button>
                        </div>
                    </div>
                    <iframe id="template_preview" style="flex:1;border:1px solid #ccd0d4;width:100%;height:500px;"></iframe>
                </div>
                <div id="palmerita-feedback" style="display:none;"></div>
                <?php submit_button(__('Save Templates','palmerita-subscriptions')); ?>
            </form>
        </div>
        <style>
        .palmerita-big-tabs .nav-tab {font-size:1.1em;padding:10px 18px 10px 12px;min-width:160px;}
        .palmerita-template-panel {margin-bottom:10px;}
        .palmerita-restore-btn {background:#f3f4f6;border:1px solid #ccd0d4;transition:background .2s;}
        .palmerita-restore-btn:hover {background:#e0e7ef;}
        .animated.fadeIn {animation:fadeIn .7s;}
        @keyframes fadeIn {from{opacity:0;}to{opacity:1;}}
        #palmerita-feedback {margin-top:10px;}
        
        /* Mejorar el layout del editor visual */
        #palmerita-template-flex {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            min-height: 600px;
        }
        
        #template_panels {
            flex: 1;
            min-width: 400px;
        }
        
        .palmerita-template {
            width: 100%;
            min-height: 400px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 10px;
        }
        
        #template_preview {
            flex: 1;
            min-width: 400px;
            background: white;
            border-radius: 4px;
        }
        
        /* Responsivo */
        @media (max-width: 1200px) {
            #palmerita-template-flex {
                flex-direction: column;
            }
            
            #template_preview {
                height: 400px;
                min-height: 400px;
            }
        }
        </style>
        <script>
        jQuery(function($){
            // Tabs
            $('#palmerita-template-tabs').on('click','a',function(e){
                e.preventDefault();
                $('#palmerita-template-tabs a').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.palmerita-template-panel').hide();
                var type = $(this).attr('href').replace('#template_','');
                $('.palmerita-template-panel[data-type='+type+']').show();
                
                // Limpiar blob URL anterior al cambiar tab
                var iframe = document.getElementById('template_preview');
                if (iframe.palmeritaBlobUrl) {
                    URL.revokeObjectURL(iframe.palmeritaBlobUrl);
                    iframe.palmeritaBlobUrl = null;
                }
                
                updatePreview();
            });
            // Restore default
            $('.palmerita-restore-btn').on('click',function(){
                var type = $(this).data('type');
                var btn = $(this);
                btn.prop('disabled',true);
                $('#palmerita-feedback').hide();
                $.post(ajaxurl,{
                    action:'palmerita_restore_template',
                    type:type,
                    _ajax_nonce:'<?php echo wp_create_nonce('palmerita_restore_template'); ?>'
                },function(resp){
                    btn.prop('disabled',false);
                    if(resp.success && resp.data.html){
                        $('#template_'+type).val(resp.data.html).trigger('change');
                        $('#palmerita-feedback').html('<div class="notice notice-success animated fadeIn"><p><?php _e('Template restored to default!','palmerita-subscriptions'); ?></p></div>').show();
                        updatePreview();
                    }else{
                        $('#palmerita-feedback').html('<div class="notice notice-error animated fadeIn"><p>'+resp.data+'</p></div>').show();
                    }
                });
            });
            // Live preview y validación de tokens
            function updatePreview(){
                var type = $('.palmerita-template-panel:visible').data('type');
                var html = $('#template_'+type).val();
                
                console.log('=== DEBUG PREVIEW ===');
                console.log('Type:', type);
                console.log('Raw HTML length:', html.length);
                console.log('Raw HTML preview:', html.substring(0, 200));
                
                if(!html.trim()){
                    html = '<p style="text-align:center;color:#666;margin-top:40px;">'+EmailTemplates.i18n.defaultTemplate+'</p>';
                }
                
                // Decodificar entidades HTML (&lt; &gt; &amp;) para que las etiquetas <style> y otros elementos se interpreten correctamente
                var decoder = document.createElement('textarea');
                decoder.innerHTML = html;
                html = decoder.value;
                
                console.log('After decode length:', html.length);
                console.log('After decode preview:', html.substring(0, 200));
                
                // Limpiar slashes en el JS también
                html = html.replace(/\\\\/g, '\\').replace(/\\'/g, "'").replace(/\\"/g, '"');
                
                console.log('After slash clean preview:', html.substring(0, 200));
                
                html = html.replace(/{{download_url}}/g,'https://example.com/download-link');
                html = html.replace(/{{subscriber_help}}/g,EmailTemplates.i18n.helpMsg);
                
                // Verificar si ya tiene DOCTYPE y html tags
                var isFullDocument = html.toLowerCase().indexOf('<!doctype') !== -1 || html.toLowerCase().indexOf('<html') !== -1;
                
                var fullHTML;
                if (isFullDocument) {
                    // Si ya es un documento completo, usarlo tal como está
                    fullHTML = html;
                    console.log('Using as full document');
                } else {
                    // Si no, envolver en estructura básica
                    fullHTML = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Email Preview</title><style>body{margin:20px;font-family:Arial,sans-serif;}</style></head><body>' + html + '</body></html>';
                    console.log('Wrapping in basic structure');
                }
                
                console.log('Final HTML length:', fullHTML.length);
                console.log('Final HTML preview:', fullHTML.substring(0, 300));
                console.log('=== END DEBUG ===');
                
                // Usar blob URL para mejor compatibilidad
                var iframe = document.getElementById('template_preview');
                
                // Crear blob URL
                var blob = new Blob([fullHTML], {type: 'text/html'});
                var url = URL.createObjectURL(blob);
                
                // Revocar URL anterior si existe
                if (iframe.palmeritaBlobUrl) {
                    URL.revokeObjectURL(iframe.palmeritaBlobUrl);
                }
                
                // Asignar nueva URL
                iframe.src = url;
                iframe.palmeritaBlobUrl = url;
            }
            $('.palmerita-template').on('input change',updatePreview);
            updatePreview();
            
            // Limpiar blob URLs al salir de la página
            $(window).on('beforeunload', function() {
                var iframe = document.getElementById('template_preview');
                if (iframe && iframe.palmeritaBlobUrl) {
                    URL.revokeObjectURL(iframe.palmeritaBlobUrl);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Clean existing templates from accumulated slashes (one-time cleanup)
     */
    private function clean_existing_templates() {
        $templates = get_option('palmerita_email_templates', array());
        $cleaned = false;
        
        foreach ($templates as $key => $template) {
            if (!empty($template) && (strpos($template, '\\\\') !== false || strpos($template, "\\'") !== false)) {
                $original = $template;
                // Limpiar slashes múltiples
                while (strpos($template, '\\\\') !== false) {
                    $template = stripslashes($template);
                }
                $templates[$key] = $template;
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            update_option('palmerita_email_templates', $templates);
        }
    }
    
    /**
     * Force restore templates that were damaged by wp_kses_post
     */
    private function force_restore_templates_with_css() {
        // Restaurar solo si alguna plantilla carece de estilos (no contiene <style>) o está vacía
        $templates = get_option('palmerita_email_templates', array());
        $needs_restore = false;
        foreach ($templates as $tmpl) {
            if (empty($tmpl) || strpos($tmpl, '<style') === false) {
                $needs_restore = true;
                break;
            }
        }

        if (!$needs_restore) {
            return; // Todo bien, no restaurar
        }

        $default_templates = array();

        if (method_exists('PalmeritaDownloadManager', 'get_download_email_template')) {
            $default_templates['cv'] = PalmeritaDownloadManager::get_download_email_template('{{download_url}}');
        }
        if (method_exists('PalmeritaDownloadManager', 'get_zip_email_template')) {
            $default_templates['file'] = PalmeritaDownloadManager::get_zip_email_template('{{download_url}}');
        }
        if (method_exists('PalmeritaDownloadManager', 'get_promo_welcome_email_template')) {
            $default_templates['promo'] = PalmeritaDownloadManager::get_promo_welcome_email_template();
        }

        if (!empty($default_templates)) {
            update_option('palmerita_email_templates', $default_templates);
        }
    }

    // AJAX: restaurar plantilla por defecto
    public function ajax_restore_template(){
        check_ajax_referer('palmerita_restore_template');
        if(!current_user_can('manage_options')){
            wp_send_json_error(__('Permission denied','palmerita-subscriptions'));
        }
        $type = $_POST['type'] ?? '';
        $html = '';
        if($type==='cv' && method_exists('PalmeritaDownloadManager','get_download_email_template')){
            $html = PalmeritaDownloadManager::get_download_email_template('{{download_url}}');
        }elseif($type==='file' && method_exists('PalmeritaDownloadManager','get_zip_email_template')){
            $html = PalmeritaDownloadManager::get_zip_email_template('{{download_url}}');
        }elseif($type==='promo' && method_exists('PalmeritaDownloadManager','get_promo_welcome_email_template')){
            $html = PalmeritaDownloadManager::get_promo_welcome_email_template();
        }
        if($html){
            wp_send_json_success(['html'=>$html]);
        }else{
            wp_send_json_error(__('No default template found.','palmerita-subscriptions'));
        }
    }

    private function log_email_delivery($type, $to, $subject, $success, $error = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'palmerita_email_log';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email_to VARCHAR(255),
            subject VARCHAR(255),
            type VARCHAR(32),
            success TINYINT(1),
            error TEXT,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) CHARSET=utf8mb4;");
        $wpdb->insert($table, [
            'email_to' => $to,
            'subject' => $subject,
            'type' => $type,
            'success' => $success ? 1 : 0,
            'error' => $error,
        ]);
    }

    /**
     * Render Email Log page
     */
    public function email_log_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'palmerita_email_log';
        $per_page = 30;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;
        $where = '1=1';
        $params = [];
        // Filtros
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $success = isset($_GET['success']) ? intval($_GET['success']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        if ($type) { $where .= ' AND type = %s'; $params[] = $type; }
        if ($success !== '') { $where .= ' AND success = %d'; $params[] = $success; }
        if ($search) { $where .= ' AND (email_to LIKE %s OR subject LIKE %s)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where", ...$params));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY sent_at DESC LIMIT %d OFFSET %d", ...array_merge($params, [$per_page, $offset])));
        $types = ['cv'=>'CV','file'=>'File','promo'=>'Promo'];
        echo '<div class="wrap"><h1>Email Log</h1>';
        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="palmerita-email-log" />';
        echo '<select name="type"><option value="">All Types</option>';
        foreach($types as $k=>$v) echo '<option value="'.$k.'"'.($type===$k?' selected':'').'>'.$v.'</option>';
        echo '</select> ';
        echo '<select name="success"><option value="">All Status</option><option value="1"'.($success==='1'?' selected':'').'>Success</option><option value="0"'.($success==='0'?' selected':'').'>Failed</option></select> ';
        echo '<input type="search" name="s" value="'.esc_attr($search).'" placeholder="Search email or subject..." /> ';
        echo '<button class="button">Filter</button>';
        echo '</form>';
        echo '<table class="widefat fixed striped"><thead><tr><th>Date</th><th>Type</th><th>Email To</th><th>Subject</th><th>Status</th><th>Error</th></tr></thead><tbody>';
        if($rows){
            foreach($rows as $row){
                echo '<tr>';
                echo '<td>'.esc_html($row->sent_at).'</td>';
                echo '<td>'.esc_html($types[$row->type] ?? $row->type).'</td>';
                echo '<td>'.esc_html($row->email_to).'</td>';
                echo '<td>'.esc_html($row->subject).'</td>';
                echo '<td>'.($row->success?'<span style="color:green;">✔</span>':'<span style="color:red;">✖</span>').'</td>';
                echo '<td>'.esc_html($row->error).'</td>';
                echo '</tr>';
            }
        }else{
            echo '<tr><td colspan="6">No log entries found.</td></tr>';
        }
        echo '</tbody></table>';
        // Paginación
        $total_pages = ceil($total/$per_page);
        if($total_pages>1){
            echo '<div style="margin-top:16px;">';
            for($i=1;$i<=$total_pages;$i++){
                if($i==$paged) echo '<strong>'.$i.'</strong> ';
                else echo '<a href="'.esc_url(add_query_arg(['paged'=>$i])).'">'.$i.'</a> ';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Clean template content from excessive slashes and sanitize
     */
    private function clean_template_content($content) {
        if (empty($content)) {
            return '';
        }
        
        // Primero eliminar slashes múltiples
        while (strpos($content, '\\\\') !== false) {
            $content = stripslashes($content);
        }
        
        // Para administradores, guardar HTML completo sin aplicar wp_kses que remueve head/style
        // Aún podemos balancear etiquetas con wp_kses_stripslashes (pero ya hicimos stripslashes)
        return $content;
    }
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function palmerita_subscriptions_run() {
    $plugin = PalmeritaSubscriptions::get_instance();
}
palmerita_subscriptions_run(); 
