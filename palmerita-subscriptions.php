<?php
/**
 * Plugin Name: Digital Nomad Subscriptions
 * Plugin URI: https://palmeritaproductions.com
 * Description: Handles CV and promotion subscriptions with modals and database storage.
 * Version: 1.2.0
 * Author: Hanaley Mosley - Palmerita Productions
 * Author URI: https://palmeritaproductions.com
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
        
        // Hook PHPMailer to send via custom SMTP if configured
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));

        add_action('wp_ajax_palmerita_send_test_email', array($this, 'send_test_email'));
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
                    PalmeritaDownloadManager::send_download_email($email, $download_url);
                }
            } elseif ($type === 'plugin') {
                $subscription_id = $result;
                $download_url = PalmeritaDownloadManager::generate_download_link($email, $subscription_id);
                if ($download_url) {
                    PalmeritaDownloadManager::send_zip_email($email, $download_url);
                }
            }
            
            wp_send_json_success(array(
                'message' => $type === 'cv' ? 
                    __('Thank you! We have sent a secure viewing link to your email address. You can view and download my CV directly in your browser. Please check your spam folder if you don\'t see it in your inbox.', 'palmerita-subscriptions') :
                    ($type === 'promo' ? __('Excellent! We will keep you informed about our promotions.', 'palmerita-subscriptions') : __('Thank you! We have emailed you the download link for the plugin.', 'palmerita-subscriptions'))
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

        // Helper to build style
        $style_attr = function($bg,$txt=''){
            $css = '';
            if($bg)  $css .= 'background-color:'.esc_attr($bg).';';
            if($txt) $css .= 'color:'.esc_attr($txt).';';
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
        // Handle upload
        if (isset($_POST['pal_zip_upload_nonce']) && wp_verify_nonce($_POST['pal_zip_upload_nonce'], 'pal_zip_upload')) {
            if (!empty($_FILES['plugin_zip']['name'])) {
                $file = $_FILES['plugin_zip'];
                $is_zip = false;
                // Allow common ZIP mime types
                if (!empty($file['type']) && strpos($file['type'], 'zip') !== false) {
                    $is_zip = true;
                }
                // Fallback: validate by extension if mime not reliable
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === 'zip') {
                    $is_zip = true;
                }

                if ($is_zip) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    $overrides = array('test_form' => false);
                    $movefile  = wp_handle_upload($file, $overrides);
                    if ($movefile && empty($movefile['error'])) {
                        update_option('palmerita_plugin_zip_url', $movefile['url']);
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('ZIP uploaded successfully.', 'palmerita-subscriptions') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($movefile['error']) . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Please upload a valid .zip file.', 'palmerita-subscriptions') . '</p></div>';
                }
            }
        }

        $current_url = esc_url($this->get_plugin_zip_url());
        ?>
        <div class="wrap">
            <h1><?php _e('File Manager', 'palmerita-subscriptions'); ?></h1>
            <p><?php _e('Upload a ZIP package to share via the subscription form. The file will be served securely with expiring links.', 'palmerita-subscriptions'); ?></p>

            <table class="form-table" role="presentation"><tbody>
                <tr>
                    <th scope="row"><?php _e('Current ZIP', 'palmerita-subscriptions'); ?></th>
                    <td>
                        <?php if ($current_url) : ?>
                            <a href="<?php echo $current_url; ?>" target="_blank"><?php echo basename($current_url); ?></a>
                        <?php else : ?>
                            <?php _e('No ZIP uploaded yet.', 'palmerita-subscriptions'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody></table>

            <h2><?php _e('Upload New ZIP', 'palmerita-subscriptions'); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pal_zip_upload', 'pal_zip_upload_nonce'); ?>
                <input type="file" name="plugin_zip" accept=".zip" required />
                <?php submit_button(__('Upload', 'palmerita-subscriptions')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Helper: current plugin ZIP URL
     */
    private function get_plugin_zip_url() {
        $stored = get_option('palmerita_plugin_zip_url');
        if ($stored) return $stored;
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
     * Plugin activation
     */
    public function activate() {
        $this->create_database_tables();
        PalmeritaDownloadManager::create_downloads_table();
        flush_rewrite_rules();
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

        $style_attr = $btn_color ? 'style="background-color:'.esc_attr($btn_color).'; color:'.esc_attr($btn_text_color).'"' : '';
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
        $style_attr = $btn_color ? 'style="background-color:'.esc_attr($btn_color).'; color:'.esc_attr($btn_text_color).'"' : '';
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
        if (!in_array($page, ['palmerita-cv-list', 'palmerita-promo-list'], true)) {
            return;
        }

        // Determine nonce action and subscription type based on page
        $nonce_action   = $page === 'palmerita-cv-list' ? 'export_cv' : 'export_promo';
        $subscription_type = $page === 'palmerita-cv-list' ? 'cv' : 'promo';

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
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $subscription_type . '-subscriptions-' . date('Y-m-d') . '.csv"');
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

        $phpmailer->isSMTP();
        $phpmailer->Host       = sanitize_text_field($settings['host'] ?? '');
        $phpmailer->Port       = intval($settings['port'] ?? 587);
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = sanitize_text_field($settings['username'] ?? '');
        $phpmailer->Password   = $settings['password'] ?? '';
        $phpmailer->SMTPSecure = sanitize_text_field($settings['encryption'] ?? 'tls'); // tls/ssl/''

        if (!empty($settings['from_email'])) {
            $phpmailer->setFrom($settings['from_email'], $settings['from_name'] ?? '');
        }
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
                'recaptcha_enabled'   => isset($_POST['recaptcha_enabled']) ? 1 : 0,
                'recaptcha_site_key'  => sanitize_text_field($_POST['recaptcha_site_key'] ?? ''),
                'recaptcha_secret'    => sanitize_text_field($_POST['recaptcha_secret'] ?? ''),
                'recaptcha_threshold' => floatval($_POST['recaptcha_threshold'] ?? 0.3),
            );
            update_option('palmerita_email_settings', $new_settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'palmerita-subscriptions') . '</p></div>';
        }

        $settings = get_option('palmerita_email_settings', array());

        // Simple form UI
        ?>
        <div class="wrap">
            <h1><?php _e('Email / SMTP Settings', 'palmerita-subscriptions'); ?></h1>
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

        $sent = wp_mail($to, __('Palmerita Subscriptions – Test Email', 'palmerita-subscriptions'), __('This is a test email confirming your SMTP settings are working.', 'palmerita-subscriptions'));

        if ($sent) {
            wp_send_json_success(__('Test email sent successfully. Please check your inbox.', 'palmerita-subscriptions'));
        } else {
            wp_send_json_error(__('Failed to send test email. Check your SMTP settings.', 'palmerita-subscriptions'));
        }
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
        $style_attr  = $btn_color ? 'style="background-color:'.esc_attr($btn_color).'; color:'.esc_attr($btn_text_color).'"' : '';
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
            <form method="post" action="">
                <?php wp_nonce_field('save_modal_copy','pal_modal_copy_nonce'); ?>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th colspan="2"><h2><?php _e('CV Button','palmerita-subscriptions'); ?></h2></th></tr>
                    <tr><th><label for="cv_title"><?php _e('Title','palmerita-subscriptions'); ?></label></th><td><input type="text" name="cv_title" id="cv_title" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['cv_title'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="cv_desc"><?php _e('Description','palmerita-subscriptions'); ?></label></th><td><textarea name="cv_desc" id="cv_desc" class="large-text" rows="3"><?php echo esc_textarea( wp_unslash($copy['cv_desc'] ?? '') ); ?></textarea></td></tr>
                    <tr><th><label for="cv_btn_text"><?php _e('Button Text','palmerita-subscriptions'); ?></label></th><td><input type="text" name="cv_btn_text" id="cv_btn_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_text'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="cv_btn_icon"><?php _e('Button Icon (emoji/HTML)','palmerita-subscriptions'); ?></label></th><td><input type="text" name="cv_btn_icon" id="cv_btn_icon" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_icon'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="cv_btn_color"><?php _e('Button Color','palmerita-subscriptions'); ?></label></th><td><input type="color" name="cv_btn_color" id="cv_btn_color" value="<?php echo esc_attr( wp_unslash($copy['cv_btn_color'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="cv_text_color"><?php _e('Text Color','palmerita-subscriptions'); ?></label></th><td><input type="color" name="cv_text_color" id="cv_text_color" value="<?php echo esc_attr( wp_unslash($copy['cv_text_color'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="cv_submit_text"><?php _e('Modal Submit Button Text','palmerita-subscriptions'); ?></label></th><td><input type="text" name="cv_submit_text" id="cv_submit_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['cv_submit_text'] ?? '') ); ?>" placeholder="<?php _e('e.g., Send CV Link','palmerita-subscriptions'); ?>" /></td></tr>

                    <tr><th colspan="2"><h2><?php _e('Promotions Button','palmerita-subscriptions'); ?></h2></th></tr>
                    <tr><th><label for="promo_title"><?php _e('Title','palmerita-subscriptions'); ?></label></th><td><input type="text" name="promo_title" id="promo_title" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['promo_title'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="promo_desc"><?php _e('Description','palmerita-subscriptions'); ?></label></th><td><textarea name="promo_desc" id="promo_desc" class="large-text" rows="3"><?php echo esc_textarea( wp_unslash($copy['promo_desc'] ?? '') ); ?></textarea></td></tr>
                    <tr><th><label for="promo_btn_text"><?php _e('Button Text','palmerita-subscriptions'); ?></label></th><td><input type="text" name="promo_btn_text" id="promo_btn_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_text'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="promo_btn_icon"><?php _e('Button Icon','palmerita-subscriptions'); ?></label></th><td><input type="text" name="promo_btn_icon" id="promo_btn_icon" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_icon'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="promo_btn_color"><?php _e('Button Color','palmerita-subscriptions'); ?></label></th><td><input type="color" name="promo_btn_color" id="promo_btn_color" value="<?php echo esc_attr( wp_unslash($copy['promo_btn_color'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="promo_text_color"><?php _e('Text Color','palmerita-subscriptions'); ?></label></th><td><input type="color" name="promo_text_color" id="promo_text_color" value="<?php echo esc_attr( wp_unslash($copy['promo_text_color'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="promo_submit_text"><?php _e('Modal Submit Button Text','palmerita-subscriptions'); ?></label></th><td><input type="text" name="promo_submit_text" id="promo_submit_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['promo_submit_text'] ?? '') ); ?>" placeholder="<?php _e('e.g., Subscribe Now','palmerita-subscriptions'); ?>" /></td></tr>

                    <tr><th colspan="2"><h2><?php _e('File Button','palmerita-subscriptions'); ?></h2></th></tr>
                    <tr><th><label for="file_title"><?php _e('Title','palmerita-subscriptions'); ?></label></th><td><input type="text" name="file_title" id="file_title" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['file_title'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="file_desc"><?php _e('Description','palmerita-subscriptions'); ?></label></th><td><textarea name="file_desc" id="file_desc" class="large-text" rows="3"><?php echo esc_textarea( wp_unslash($copy['file_desc'] ?? '') ); ?></textarea></td></tr>
                    <tr><th><label for="file_btn_text"><?php _e('Button Text','palmerita-subscriptions'); ?></label></th><td><input type="text" name="file_btn_text" id="file_btn_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['file_btn_text'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="file_btn_icon"><?php _e('Button Icon','palmerita-subscriptions'); ?></label></th><td><input type="text" name="file_btn_icon" id="file_btn_icon" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['file_btn_icon'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="file_btn_color"><?php _e('Button Color','palmerita-subscriptions'); ?></label></th><td><input type="color" name="file_btn_color" id="file_btn_color" value="<?php echo esc_attr( wp_unslash($copy['file_btn_color'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="file_text_color"><?php _e('Text Color','palmerita-subscriptions'); ?></label></th><td><input type="color" name="file_text_color" id="file_text_color" value="<?php echo esc_attr( wp_unslash($copy['file_text_color'] ?? '') ); ?>" /></td></tr>
                    <tr><th><label for="file_submit_text"><?php _e('Modal Submit Button Text','palmerita-subscriptions'); ?></label></th><td><input type="text" name="file_submit_text" id="file_submit_text" class="regular-text" value="<?php echo esc_attr( wp_unslash($copy['file_submit_text'] ?? '') ); ?>" placeholder="<?php _e('e.g., Get Download Link','palmerita-subscriptions'); ?>" /></td></tr>
                </tbody></table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
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
