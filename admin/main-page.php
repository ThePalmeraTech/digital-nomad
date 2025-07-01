<?php
/**
 * Admin Main Page
 * Dashboard overview for Digital Nomad Subscriptions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'palmerita_subscriptions';

// Get statistics
$cv_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'cv' AND status = 'active'");
$promo_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'promo' AND status = 'active'");
$plugin_downloads = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}palmerita_downloads d
     JOIN {$wpdb->prefix}palmerita_subscriptions s ON s.id = d.subscription_id
     WHERE s.type = 'plugin' AND d.status = 'active'"
);
$total_count = $cv_count + $promo_count + $plugin_downloads;

// Get recent subscriptions
$recent_subs = $wpdb->get_results("
    SELECT * FROM $table_name 
    WHERE status = 'active' 
    ORDER BY date_created DESC 
    LIMIT 10
");

// Get daily stats for last 7 days
$daily_stats = $wpdb->get_results("
    SELECT 
        DATE(date_created) as date,
        type,
        COUNT(*) as count
    FROM $table_name 
    WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND status = 'active'
    GROUP BY DATE(date_created), type
    ORDER BY date DESC
");

// --- FILE MANAGER SECTION ---
$file_dir = PALMERITA_SUBS_PLUGIN_DIR . 'assets/files/';
if (!file_exists($file_dir)) { mkdir($file_dir, 0755, true); }
$file_name = get_option('palmerita_file_custom_name', 'My-File.zip');
$file_path = $file_dir . $file_name;
$file_exists = file_exists($file_path);
$file_uploaded = get_option('palmerita_file_uploaded', '');

// Handle file upload
if (isset($_POST['upload_file']) && wp_verify_nonce($_POST['_wpnonce'], 'upload_file')) {
    if (!empty($_FILES['dist_file']['name'])) {
        $custom_name = !empty($_POST['file_custom_name']) ? sanitize_file_name($_POST['file_custom_name']) : 'My-File';
        $ext = strtolower(pathinfo($_FILES['dist_file']['name'], PATHINFO_EXTENSION));
        $allowed = array('zip','pdf','docx');
        if (!in_array($ext, $allowed)) {
            echo '<div class="notice notice-error"><p>Only ZIP, PDF, or DOCX files are allowed.</p></div>';
        } else if ($_FILES['dist_file']['size'] > 20 * 1024 * 1024) {
            echo '<div class="notice notice-error"><p>File size must be less than 20MB.</p></div>';
        } else {
            // Remove all existing files in the folder
            foreach (glob($file_dir . '*') as $old_file) {
                unlink($old_file);
            }
            $file_name = $custom_name . '.' . $ext;
            $file_path = $file_dir . $file_name;
            if (move_uploaded_file($_FILES['dist_file']['tmp_name'], $file_path)) {
                update_option('palmerita_file_uploaded', current_time('mysql'));
                update_option('palmerita_file_custom_name', $file_name);
                echo '<div class="notice notice-success"><p>File uploaded successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error uploading file.</p></div>';
            }
        }
    }
}
// Handle file deletion
if (isset($_POST['delete_file']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_file')) {
    $file_name = get_option('palmerita_file_custom_name', 'My-File.zip');
    $file_path = $file_dir . $file_name;
    if (file_exists($file_path)) {
        unlink($file_path);
        delete_option('palmerita_file_uploaded');
        delete_option('palmerita_file_custom_name');
        echo '<div class="notice notice-success"><p>File deleted successfully!</p></div>';
    }
}
$file_name = get_option('palmerita_file_custom_name', 'My-File.zip');
$file_path = $file_dir . $file_name;
$file_exists = file_exists($file_path);
$file_uploaded = get_option('palmerita_file_uploaded', '');
// --- FILE MANAGER UI ---
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-email-alt"></span>
        <?php _e('Digital Nomad Subscriptions', 'palmerita-subscriptions'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=palmerita-cv-list'); ?>" class="page-title-action">
        <?php _e('View CV List', 'palmerita-subscriptions'); ?>
    </a>
    
    <a href="<?php echo admin_url('admin.php?page=palmerita-promo-list'); ?>" class="page-title-action">
        <?php _e('View Promotions List', 'palmerita-subscriptions'); ?>
    </a>
    
    <a href="<?php echo admin_url('admin.php?page=palmerita-terms'); ?>" class="page-title-action">
        <?php _e('Terms & Conditions', 'palmerita-subscriptions'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Statistics Cards -->
    <div class="palmerita-stats-grid">
        <div class="palmerita-stat-card total">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <h3><?php echo number_format($total_count); ?></h3>
                <p><?php _e('Total Subscriptions', 'palmerita-subscriptions'); ?></p>
            </div>
        </div>
        
        <div class="palmerita-stat-card cv">
            <div class="stat-icon">📄</div>
            <div class="stat-content">
                <h3><?php echo number_format($cv_count); ?></h3>
                <p><?php _e('CV Requests', 'palmerita-subscriptions'); ?></p>
            </div>
        </div>
        
        <div class="palmerita-stat-card promo">
            <div class="stat-icon">🎯</div>
            <div class="stat-content">
                <h3><?php echo number_format($promo_count); ?></h3>
                <p><?php _e('Promotion Subscriptions', 'palmerita-subscriptions'); ?></p>
            </div>
        </div>
        
        <div class="palmerita-stat-card plugin">
            <div class="stat-icon">🛠️</div>
            <div class="stat-content">
                <h3><?php echo number_format($plugin_downloads); ?></h3>
                <p><?php _e('File Downloads', 'palmerita-subscriptions'); ?></p>
            </div>
        </div>
    </div>

    <div class="palmerita-admin-grid">
        <!-- Recent Subscriptions -->
        <div class="palmerita-admin-section">
            <div class="section-header">
                <h2><?php _e('Recent Subscriptions', 'palmerita-subscriptions'); ?></h2>
                <span class="section-count"><?php echo count($recent_subs); ?> latest</span>
            </div>
            
            <?php if ($recent_subs): ?>
                <div class="palmerita-recent-list">
                    <?php foreach ($recent_subs as $sub): ?>
                        <div class="recent-item <?php echo esc_attr($sub->type); ?>">
                            <div class="item-icon">
                                <?php echo $sub->type === 'cv' ? '📄' : ($sub->type === 'promo' ? '🎯' : '🛠️'); ?>
                            </div>
                            <div class="item-content">
                                <strong><?php echo esc_html($sub->email); ?></strong>
                                <div class="item-meta">
                                    <span class="type-badge <?php echo esc_attr($sub->type); ?>">
                                        <?php echo $sub->type === 'cv' ? __('CV', 'palmerita-subscriptions') : ($sub->type === 'promo' ? __('Promotions', 'palmerita-subscriptions') : __('Plugin', 'palmerita-subscriptions')); ?>
                                    </span>
                                    <span class="date">
                                        <?php echo human_time_diff(strtotime($sub->date_created), current_time('timestamp')) . ' ' . __('ago', 'palmerita-subscriptions'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p><?php _e('No subscriptions yet.', 'palmerita-subscriptions'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="palmerita-admin-section">
            <div class="section-header">
                <h2><?php _e('Quick Actions', 'palmerita-subscriptions'); ?></h2>
            </div>
            
            <div class="quick-actions">
                <a href="<?php echo admin_url('admin.php?page=palmerita-cv-list'); ?>" class="quick-action cv">
                    <span class="action-icon">📄</span>
                    <span class="action-text"><?php _e('Manage CV List', 'palmerita-subscriptions'); ?></span>
                    <span class="action-count"><?php echo $cv_count; ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=palmerita-promo-list'); ?>" class="quick-action promo">
                    <span class="action-icon">🎯</span>
                    <span class="action-text"><?php _e('Manage Promotions', 'palmerita-subscriptions'); ?></span>
                    <span class="action-count"><?php echo $promo_count; ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=palmerita-file-manager'); ?>" class="quick-action plugin">
                    <span class="action-icon">🛠️</span>
                    <span class="action-text"><?php _e('Manage File', 'palmerita-subscriptions'); ?></span>
                    <span class="action-count"><?php echo $plugin_downloads; ?></span>
                </a>
                
                <div class="quick-action shortcode" style="flex-direction:column;align-items:flex-start;gap:6px;">
                    <span class="action-icon">⚡</span>
                    <span class="action-text"><?php _e('Available Shortcodes:', 'palmerita-subscriptions'); ?></span>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <code>[palmerita_subscription_buttons]</code>
                        <code>[palmerita_cv_button]</code>
                        <code>[palmerita_promo_button]</code>
                        <code>[palmerita_file_button]</code>
                    </div>
                    <p style="flex-basis:100%;max-width:100%;margin-top:6px;font-size:12px;line-height:1.4;">
                        <?php _e('Tip: all shortcodes accept', 'palmerita-subscriptions'); ?> <code>class="my-css classes"</code> <?php _e('to add your own CSS classes, and', 'palmerita-subscriptions'); ?> <code>no_default="1"</code> <?php _e('to remove the plugin&rsquo;s default styling classes.', 'palmerita-subscriptions'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Instructions -->
    <div class="palmerita-admin-section full-width">
        <div class="section-header">
            <h2><?php _e('How to use the plugin', 'palmerita-subscriptions'); ?></h2>
        </div>
        
        <div class="usage-grid">
            <div class="usage-step">
                <div class="step-number">1</div>
                <h3><?php _e('Add Buttons', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('Use the shortcode', 'palmerita-subscriptions'); ?> <code>[palmerita_subscription_buttons]</code> <?php _e('on any page, post or widget.', 'palmerita-subscriptions'); ?></p>
            </div>
            
            <div class="usage-step">
                <div class="step-number">2</div>
                <h3><?php _e('Customize Style', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('You can use different styles:', 'palmerita-subscriptions'); ?> <code>style="hero"</code> <?php _e('or', 'palmerita-subscriptions'); ?> <code>style="sidebar"</code>. <?php _e('Additionally, pass', 'palmerita-subscriptions'); ?> <code>class="btn btn-primary"</code> <?php _e('to apply your theme classes and', 'palmerita-subscriptions'); ?> <code>no_default="1"</code> <?php _e('to omit builtin styles.', 'palmerita-subscriptions'); ?></p>
            </div>
            
            <div class="usage-step">
                <div class="step-number">3</div>
                <h3><?php _e('Manage Lists', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('Go to the CV or Promotions pages to view, export and manage subscriptions.', 'palmerita-subscriptions'); ?></p>
            </div>
        </div>
    </div>

    <!-- File Manager -->
    <div class="palmerita-admin-section full-width">
        <div class="section-header">
            <h2><?php _e('File Manager', 'palmerita-subscriptions'); ?></h2>
        </div>
        
        <div class="palmerita-file-manager">
            <?php if ($file_exists): ?>
                <div class="file-status-card success">
                    <div class="status-icon">✅</div>
                    <div class="status-content">
                        <h2><?php _e('File Ready for Download', 'palmerita-subscriptions'); ?></h2>
                        <p><?php _e('Your file is uploaded and ready to be shared.', 'palmerita-subscriptions'); ?></p>
                        <div class="file-details">
                            <div class="detail-item"><strong><?php _e('File Name:', 'palmerita-subscriptions'); ?></strong> <span><?php echo esc_html($file_name); ?></span></div>
                            <div class="detail-item"><strong><?php _e('Uploaded:', 'palmerita-subscriptions'); ?></strong> <span><?php echo $file_uploaded ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($file_uploaded)) : '-'; ?></span></div>
                            <div class="detail-item"><strong><?php _e('Direct Download:', 'palmerita-subscriptions'); ?></strong> <code><?php echo PALMERITA_SUBS_PLUGIN_URL . 'assets/files/' . $file_name; ?></code></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="file-status-card warning">
                    <div class="status-icon">⚠️</div>
                    <div class="status-content">
                        <h2><?php _e('No File Uploaded', 'palmerita-subscriptions'); ?></h2>
                        <p><?php _e('Upload a file to start sharing it with visitors.', 'palmerita-subscriptions'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="file-upload-section">
                <h2><?php echo $file_exists ? __('Replace File', 'palmerita-subscriptions') : __('Upload File', 'palmerita-subscriptions'); ?></h2>
                <form method="post" enctype="multipart/form-data" class="file-upload-form">
                    <?php wp_nonce_field('upload_file'); ?>
                    <div class="upload-area">
                        <div class="upload-icon">🗂️</div>
                        <div class="upload-content">
                            <h3><?php _e('Select your file', 'palmerita-subscriptions'); ?></h3>
                            <p><?php _e('Choose a ZIP, PDF, or DOCX file (max 20MB)', 'palmerita-subscriptions'); ?></p>
                            <input type="file" name="dist_file" id="dist_file" accept=".zip,.pdf,.docx" required class="file-input">
                            <label for="dist_file" class="file-label"><span class="dashicons dashicons-upload"></span> <?php _e('Choose File', 'palmerita-subscriptions'); ?></label>
                            <div class="file-info" style="display: none;"><span class="file-name"></span><span class="file-size"></span></div>
                            <div style="margin-top:20px;">
                                <label for="file_custom_name"><strong><?php _e('File name (without extension):', 'palmerita-subscriptions'); ?></strong></label>
                                <input type="text" name="file_custom_name" id="file_custom_name" value="<?php echo esc_attr(str_replace(array('.zip','.pdf','.docx'),'', $file_name)); ?>" pattern="[A-Za-z0-9\-_ ]+" maxlength="60" style="width:220px;" required>
                                <span style="color:#888; font-size:0.9em;">.zip/.pdf/.docx</span>
                            </div>
                        </div>
                    </div>
                    <div class="upload-actions">
                        <input type="submit" name="upload_file" class="button button-primary button-large" value="<?php echo $file_exists ? __('Replace File', 'palmerita-subscriptions') : __('Upload File', 'palmerita-subscriptions'); ?>">
                        <?php if ($file_exists): ?>
                            <input type="submit" name="delete_file" class="button button-link-delete" value="<?php _e('Delete Current File', 'palmerita-subscriptions'); ?>" onclick="return confirm('<?php _e('Are you sure you want to delete the current file?', 'palmerita-subscriptions'); ?>')">
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.palmerita-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.palmerita-stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.palmerita-stat-card.total {
    border-left: 4px solid #6366f1;
}

.palmerita-stat-card.cv {
    border-left: 4px solid #8b5cf6;
}

.palmerita-stat-card.promo {
    border-left: 4px solid #f59e0b;
}

.palmerita-stat-card.plugin {
    border-left: 4px solid #05b358;
}

.stat-icon {
    font-size: 2rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: #1e293b;
}

.stat-content p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 0.9rem;
}

.palmerita-admin-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.palmerita-admin-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.palmerita-admin-section.full-width {
    grid-column: 1 / -1;
}

.section-header {
    background: #f8fafc;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: #1e293b;
}

.section-count {
    background: #6366f1;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
}

.palmerita-recent-list {
    padding: 20px;
}

.recent-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.recent-item:last-child {
    border-bottom: none;
}

.item-icon {
    font-size: 1.5rem;
}

.item-content {
    flex: 1;
}

.item-meta {
    display: flex;
    gap: 10px;
    margin-top: 4px;
}

.type-badge {
    background: #e2e8f0;
    color: #475569;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.type-badge.cv {
    background: #ede9fe;
    color: #7c3aed;
}

.type-badge.promo {
    background: #fef3c7;
    color: #d97706;
}

.type-badge.plugin {
    background: #d1fae5;
    color: #15803d;
}

.date {
    color: #64748b;
    font-size: 0.8rem;
}

.quick-actions {
    padding: 20px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    margin-bottom: 10px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}

.quick-action:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.quick-action.shortcode {
    background: #fef7cd;
    border-color: #fbbf24;
}

.action-icon {
    font-size: 1.2rem;
}

.action-text {
    flex: 1;
    font-weight: 500;
}

.action-count {
    background: #6366f1;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
}

.usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    padding: 20px;
}

.usage-step {
    text-align: center;
    padding: 20px;
}

.step-number {
    background: #6366f1;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto 15px;
}

.usage-step h3 {
    margin: 0 0 10px;
    color: #1e293b;
}

.usage-step code {
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    color: #7c3aed;
}

.no-data {
    padding: 40px 20px;
    text-align: center;
    color: #64748b;
}

@media (max-width: 768px) {
    .palmerita-admin-grid {
        grid-template-columns: 1fr;
    }
    
    .palmerita-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style> 