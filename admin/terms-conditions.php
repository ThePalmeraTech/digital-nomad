<?php
/**
 * Terms and Conditions Page
 * Manage terms and conditions for subscriptions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['save_terms']) && wp_verify_nonce($_POST['_wpnonce'], 'save_terms')) {
    $cv_terms = wp_kses_post($_POST['cv_terms']);
    $promo_terms = wp_kses_post($_POST['promo_terms']);
    
    update_option('palmerita_cv_terms', $cv_terms);
    update_option('palmerita_promo_terms', $promo_terms);
    
    echo '<div class="notice notice-success"><p>' . __('Terms and conditions saved successfully.', 'palmerita-subscriptions') . '</p></div>';
}

// Get current terms
$cv_terms = get_option('palmerita_cv_terms', '');
$promo_terms = get_option('palmerita_promo_terms', '');

// Set default terms if empty
if (empty($cv_terms)) {
    $cv_terms = __('By subscribing to receive our CV, you agree to:

• Receive a one-time email with a download link for our professional CV
• Your email address will be stored securely and used only for this purpose
• You may receive occasional follow-up communications about professional opportunities
• You can request removal of your data at any time by contacting us
• We will not share your email address with third parties
• The download link will expire after 7 days for security purposes

For questions about data handling, please contact: hello@palmeritaproductions.com', 'palmerita-subscriptions');
}

if (empty($promo_terms)) {
    $promo_terms = __('By subscribing to our promotions, you agree to:

• Receive periodic emails about our services, special offers, and updates
• Your email address will be stored securely in our marketing database
• You can unsubscribe at any time using the link provided in our emails
• We will not share your email address with third parties
• We may send you relevant content about web development, design trends, and our portfolio updates
• You can request complete removal of your data at any time

Frequency: We typically send 1-2 emails per month, with occasional special announcements.

For questions or to unsubscribe, contact: hello@palmeritaproductions.com', 'palmerita-subscriptions');
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-page"></span>
        <?php _e('Terms & Conditions', 'palmerita-subscriptions'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=palmerita-subscriptions'); ?>" class="page-title-action">
        <?php _e('← Back to Dashboard', 'palmerita-subscriptions'); ?>
    </a>
    
    <hr class="wp-header-end">

    <div class="palmerita-terms-container">
        <div class="palmerita-info-box">
            <h3><?php _e('About Terms & Conditions', 'palmerita-subscriptions'); ?></h3>
            <p><?php _e('These terms will be displayed to users when they subscribe. Make sure to comply with GDPR, CAN-SPAM Act, and other applicable privacy laws.', 'palmerita-subscriptions'); ?></p>
            <ul>
                <li><?php _e('Be clear about what users are subscribing to', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Explain how their data will be used', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Provide clear unsubscribe instructions', 'palmerita-subscriptions'); ?></li>
                <li><?php _e('Include your contact information', 'palmerita-subscriptions'); ?></li>
            </ul>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('save_terms'); ?>
            
            <div class="palmerita-terms-section">
                <h2><?php _e('CV Download Terms', 'palmerita-subscriptions'); ?></h2>
                <p class="description"><?php _e('Terms shown to users when they request your CV download.', 'palmerita-subscriptions'); ?></p>
                
                <?php
                wp_editor($cv_terms, 'cv_terms', array(
                    'textarea_name' => 'cv_terms',
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                    'teeny' => true,
                    'quicktags' => true
                ));
                ?>
            </div>

            <div class="palmerita-terms-section">
                <h2><?php _e('Promotions Subscription Terms', 'palmerita-subscriptions'); ?></h2>
                <p class="description"><?php _e('Terms shown to users when they subscribe to promotions and updates.', 'palmerita-subscriptions'); ?></p>
                
                <?php
                wp_editor($promo_terms, 'promo_terms', array(
                    'textarea_name' => 'promo_terms',
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                    'teeny' => true,
                    'quicktags' => true
                ));
                ?>
            </div>

            <div class="palmerita-terms-actions">
                <input type="submit" name="save_terms" class="button button-primary button-large" value="<?php _e('Save Terms & Conditions', 'palmerita-subscriptions'); ?>">
                
                <div class="palmerita-preview-links">
                    <h3><?php _e('Preview Links', 'palmerita-subscriptions'); ?></h3>
                    <p>
                        <a href="<?php echo site_url('/palmerita-terms/cv'); ?>" target="_blank" class="button">
                            <?php _e('Preview CV Terms', 'palmerita-subscriptions'); ?>
                        </a>
                        <a href="<?php echo site_url('/palmerita-terms/promo'); ?>" target="_blank" class="button">
                            <?php _e('Preview Promo Terms', 'palmerita-subscriptions'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.palmerita-terms-container {
    max-width: 1000px;
}

.palmerita-info-box {
    background: #f0f6fc;
    border: 1px solid #c3d9ff;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.palmerita-info-box h3 {
    margin-top: 0;
    color: #0073aa;
}

.palmerita-info-box ul {
    margin-left: 20px;
}

.palmerita-terms-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.palmerita-terms-section h2 {
    margin-top: 0;
    color: #1e293b;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 10px;
}

.palmerita-terms-actions {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
}

.palmerita-preview-links {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.palmerita-preview-links h3 {
    margin-bottom: 10px;
}

.palmerita-preview-links .button {
    margin: 0 5px;
}

@media (max-width: 768px) {
    .palmerita-terms-container {
        padding: 0 10px;
    }
    
    .palmerita-terms-section {
        padding: 15px;
    }
}
</style> 