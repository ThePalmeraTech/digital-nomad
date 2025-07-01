<?php
/**
 * Manual Database Upgrade Script for Tracking Features
 * 
 * This script updates the existing palmerita_downloads table to add tracking fields.
 * Run this ONCE after updating the plugin to enable link tracking.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'palmerita-subscriptions'));
}

$upgrade_success = false;
$upgrade_message = '';

// Handle the upgrade request
if (isset($_POST['perform_upgrade']) && wp_verify_nonce($_POST['upgrade_nonce'], 'palmerita_upgrade_tracking')) {
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'palmerita_downloads';
    
    try {
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            $upgrade_message = 'Downloads table does not exist. Please activate the plugin first.';
        } else {
            
            // Get current table structure
            $columns = $wpdb->get_col("DESCRIBE $table_name");
            $columns_added = array();
            
            // Add tracking columns if they don't exist
            if (!in_array('clicked', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN clicked tinyint(1) DEFAULT 0");
                if ($result !== false) $columns_added[] = 'clicked';
            }
            
            if (!in_array('first_clicked', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN first_clicked datetime NULL");
                if ($result !== false) $columns_added[] = 'first_clicked';
            }
            
            if (!in_array('click_count', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN click_count int(11) DEFAULT 0");
                if ($result !== false) $columns_added[] = 'click_count';
            }
            
            if (!in_array('last_clicked', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN last_clicked datetime NULL");
                if ($result !== false) $columns_added[] = 'last_clicked';
            }
            
            if (!in_array('user_agent', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN user_agent text NULL");
                if ($result !== false) $columns_added[] = 'user_agent';
            }
            
            if (!in_array('ip_address', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN ip_address varchar(45) NULL");
                if ($result !== false) $columns_added[] = 'ip_address';
            }
            
            if (!in_array('referrer', $columns)) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN referrer varchar(255) NULL");
                if ($result !== false) $columns_added[] = 'referrer';
            }
            
            // Add indexes (ignore errors if they already exist)
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_clicked (clicked)");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_status (status)");
            
            // Update version
            update_option('palmerita_tracking_version', '1.1.0');
            
            if (empty($columns_added)) {
                $upgrade_message = 'Database is already up to date. All tracking columns exist.';
                $upgrade_success = true;
            } else {
                $upgrade_message = 'Successfully added tracking columns: ' . implode(', ', $columns_added);
                $upgrade_success = true;
            }
            
            // Log the upgrade
            error_log('Palmerita: Manual database upgrade completed. Added columns: ' . implode(', ', $columns_added));
        }
        
    } catch (Exception $e) {
        $upgrade_message = 'Error during upgrade: ' . $e->getMessage();
        error_log('Palmerita: Database upgrade error: ' . $e->getMessage());
    }
}

// Check current database status
global $wpdb;
$table_name = $wpdb->prefix . 'palmerita_downloads';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

if ($table_exists) {
    $columns = $wpdb->get_col("DESCRIBE $table_name");
    $tracking_columns = array('clicked', 'first_clicked', 'click_count', 'last_clicked', 'user_agent', 'ip_address', 'referrer');
    $missing_columns = array_diff($tracking_columns, $columns);
    $needs_upgrade = !empty($missing_columns);
} else {
    $needs_upgrade = true;
    $missing_columns = array('table does not exist');
}

$current_version = get_option('palmerita_tracking_version', '0');
?>

<div class="wrap">
    <h1><?php _e('Palmerita Subscriptions - Database Upgrade', 'palmerita-subscriptions'); ?></h1>
    
    <?php if ($upgrade_message): ?>
        <div class="notice notice-<?php echo $upgrade_success ? 'success' : 'error'; ?> is-dismissible">
            <p><strong><?php echo esc_html($upgrade_message); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <div class="tracking-upgrade-status">
        <h2><?php _e('Link Tracking System Status', 'palmerita-subscriptions'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Current Version', 'palmerita-subscriptions'); ?></th>
                <td><code><?php echo esc_html($current_version); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Required Version', 'palmerita-subscriptions'); ?></th>
                <td><code>1.1.0</code></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Database Status', 'palmerita-subscriptions'); ?></th>
                <td>
                    <?php if ($needs_upgrade): ?>
                        <span style="color: #dc3232; font-weight: bold;">❌ <?php _e('Upgrade Required', 'palmerita-subscriptions'); ?></span>
                    <?php else: ?>
                        <span style="color: #46b450; font-weight: bold;">✅ <?php _e('Up to Date', 'palmerita-subscriptions'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($needs_upgrade): ?>
            <tr>
                <th scope="row"><?php _e('Missing Columns', 'palmerita-subscriptions'); ?></th>
                <td>
                    <code style="background: #f0f0f1; padding: 4px 8px; border-radius: 3px;">
                        <?php echo esc_html(implode(', ', $missing_columns)); ?>
                    </code>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <?php if ($needs_upgrade): ?>
    <div class="upgrade-section">
        <h2><?php _e('Perform Database Upgrade', 'palmerita-subscriptions'); ?></h2>
        
        <div class="notice notice-info">
            <p><strong><?php _e('What this upgrade does:', 'palmerita-subscriptions'); ?></strong></p>
            <ul>
                <li>• <?php _e('Adds click tracking columns to the downloads table', 'palmerita-subscriptions'); ?></li>
                <li>• <?php _e('Enables link analytics and detailed reporting', 'palmerita-subscriptions'); ?></li>
                <li>• <?php _e('Tracks user engagement with download links', 'palmerita-subscriptions'); ?></li>
                <li>• <?php _e('Adds performance indexes for better query speed', 'palmerita-subscriptions'); ?></li>
            </ul>
        </div>
        
        <div class="notice notice-warning">
            <p><strong><?php _e('Important:', 'palmerita-subscriptions'); ?></strong> <?php _e('This will modify your database structure. Please backup your database before proceeding.', 'palmerita-subscriptions'); ?></p>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('palmerita_upgrade_tracking', 'upgrade_nonce'); ?>
            <p>
                <input type="submit" name="perform_upgrade" class="button button-primary button-large" 
                       value="<?php _e('🚀 Upgrade Database for Link Tracking', 'palmerita-subscriptions'); ?>"
                       onclick="return confirm('<?php _e('Are you sure you want to upgrade the database? Please ensure you have a backup.', 'palmerita-subscriptions'); ?>')">
            </p>
        </form>
    </div>
    <?php else: ?>
    <div class="upgrade-complete">
        <h2><?php _e('✅ System Ready', 'palmerita-subscriptions'); ?></h2>
        <p><?php _e('Your database is up to date and ready for link tracking!', 'palmerita-subscriptions'); ?></p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=palmerita-link-analytics'); ?>" class="button button-primary">
                <?php _e('📊 View Link Analytics', 'palmerita-subscriptions'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="tracking-features">
        <h2><?php _e('🎯 New Tracking Features', 'palmerita-subscriptions'); ?></h2>
        <div class="feature-grid">
            <div class="feature-card">
                <h3>📊 <?php _e('Link Analytics Dashboard', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('Comprehensive dashboard showing click rates, engagement metrics, and user behavior patterns.', 'palmerita-subscriptions'); ?></p>
            </div>
            
            <div class="feature-card">
                <h3>🔍 <?php _e('Individual Link Tracking', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('Track each unique link: creation date, expiration, click status, IP addresses, and user agents.', 'palmerita-subscriptions'); ?></p>
            </div>
            
            <div class="feature-card">
                <h3>📈 <?php _e('Performance Insights', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('Understand which content performs best and optimize your email campaigns accordingly.', 'palmerita-subscriptions'); ?></p>
            </div>
            
            <div class="feature-card">
                <h3>🛡️ <?php _e('Security Monitoring', 'palmerita-subscriptions'); ?></h3>
                <p><?php _e('Monitor access patterns, detect unusual activity, and ensure your links are used as intended.', 'palmerita-subscriptions'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.tracking-upgrade-status {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.upgrade-section, .upgrade-complete {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.tracking-features {
    margin: 30px 0;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.feature-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.feature-card h3 {
    margin: 0 0 10px 0;
    color: #0073aa;
}

.feature-card p {
    color: #666;
    margin: 0;
    line-height: 1.5;
}

@media (max-width: 782px) {
    .feature-grid {
        grid-template-columns: 1fr;
    }
}
</style> 