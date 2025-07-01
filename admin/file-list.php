<?php
/**
 * File Downloads List Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'palmerita_subscriptions';

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['selected_items'])) {
    if (wp_verify_nonce($_POST['bulk_nonce'], 'bulk_delete_file_subs')) {
        $selected_items = array_map('intval', $_POST['selected_items']);
        $placeholders = implode(',', array_fill(0, count($selected_items), '%d'));
        
        $query = "UPDATE $table_name SET status = 'deleted' WHERE id IN ($placeholders) AND type = 'plugin'";
        $wpdb->query($wpdb->prepare($query, $selected_items));
        
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             sprintf(__('%d file subscriptions deleted.', 'palmerita-subscriptions'), count($selected_items)) . 
             '</p></div>';
    }
}

// Handle individual delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_file_sub_' . $_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->update(
            $table_name,
            array('status' => 'deleted'),
            array('id' => $id, 'type' => 'plugin'),
            array('%s'),
            array('%d', '%s')
        );
        
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             __('File subscription deleted.', 'palmerita-subscriptions') . 
             '</p></div>';
    }
}

// Pagination
$items_per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get search term
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Build query
$where_clause = "WHERE type = 'plugin' AND status = 'active'";
$query_params = array();

if (!empty($search)) {
    $where_clause .= " AND email LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
}

// Get results
$query = "SELECT * FROM $table_name $where_clause ORDER BY date_created DESC LIMIT %d OFFSET %d";
$query_params[] = $items_per_page;
$query_params[] = $offset;

$results = $wpdb->get_results($wpdb->prepare($query, $query_params));

// Get total count for pagination
$total_query = "SELECT COUNT(*) FROM $table_name $where_clause";
$total_params = array_slice($query_params, 0, -2);
$total_items = $wpdb->get_var($wpdb->prepare($total_query, $total_params));

$total_pages = ceil($total_items / $items_per_page);

// Mostrar nombre del archivo distribuido si existe
$file_dir = PALMERITA_SUBS_PLUGIN_DIR . 'assets/';
$file_name = get_option('palmerita_file_custom_name', 'My-File.zip');
$file_path = $file_dir . $file_name;
$file_exists = file_exists($file_path);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('File Downloads List', 'palmerita-subscriptions'); ?></h1>
    
    <!-- Stats Cards -->
    <div class="palmerita-stats-grid" style="margin: 20px 0;">
        <div class="palmerita-stat-card">
            <h3><?php _e('Today', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number">
                <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'plugin' AND DATE(date_created) = CURDATE()"); ?></strong>
            </div>
        </div>
        
        <div class="palmerita-stat-card">
            <h3><?php _e('This Week', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number">
                <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'plugin' AND date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); ?></strong>
            </div>
        </div>
        
        <div class="palmerita-stat-card">
            <h3><?php _e('This Month', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number">
                <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'plugin' AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)"); ?></strong>
            </div>
        </div>
        
        <div class="palmerita-stat-card">
            <h3><?php _e('Total Active', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number">
                <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'plugin' AND status = 'active'"); ?></strong>
            </div>
        </div>
    </div>

    <!-- Search and Export -->
    <div class="tablenav top">
        <form method="get" style="float: left; margin-right: 20px;">
            <input type="hidden" name="page" value="palmerita-file-list">
            <p class="search-box">
                <label class="screen-reader-text" for="file-search-input"><?php _e('Search File Downloads', 'palmerita-subscriptions'); ?></label>
                <input type="search" id="file-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search emails...', 'palmerita-subscriptions'); ?>">
                <input type="submit" id="search-submit" class="button" value="<?php _e('Search', 'palmerita-subscriptions'); ?>">
            </p>
        </form>
        
        <div class="alignleft actions">
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=palmerita-file-list&export=csv'), 'export_file'); ?>" 
               class="button button-secondary">
                <?php _e('Export to CSV', 'palmerita-subscriptions'); ?>
            </a>
        </div>
        
        <br class="clear">
    </div>

    <!-- Bulk Actions Form -->
    <form method="post">
        <?php wp_nonce_field('bulk_delete_file_subs', 'bulk_nonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'palmerita-subscriptions'); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'palmerita-subscriptions'); ?></option>
                    <option value="bulk_delete"><?php _e('Delete', 'palmerita-subscriptions'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'palmerita-subscriptions'); ?>">
            </div>
        </div>

        <!-- Data Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'palmerita-subscriptions'); ?></label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-email column-primary">
                        <?php _e('Email', 'palmerita-subscriptions'); ?>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <?php _e('Date Subscribed', 'palmerita-subscriptions'); ?>
                    </th>
                    <th scope="col" class="manage-column column-ip">
                        <?php _e('IP Address', 'palmerita-subscriptions'); ?>
                    </th>
                    <th scope="col" class="manage-column column-downloads">
                        <?php _e('Downloads', 'palmerita-subscriptions'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'palmerita-subscriptions'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div class="palmerita-empty-state">
                                <h3><?php _e('No file downloads yet', 'palmerita-subscriptions'); ?></h3>
                                <p><?php _e('File download subscriptions will appear here when users request downloads.', 'palmerita-subscriptions'); ?></p>
                                <div style="margin-top: 20px;">
                                    <span style="font-size: 48px;">🗂️</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $subscription): ?>
                        <?php
                        // Get download count from downloads table
                        $downloads_table = $wpdb->prefix . 'palmerita_downloads';
                        $download_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT SUM(downloads) FROM $downloads_table WHERE email = %s AND subscription_id = %d",
                            $subscription->email,
                            $subscription->id
                        ));
                        $download_count = $download_count ? $download_count : 0;
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="selected_items[]" value="<?php echo esc_attr($subscription->id); ?>">
                            </th>
                            <td class="column-email column-primary" data-colname="<?php _e('Email', 'palmerita-subscriptions'); ?>">
                                <strong><?php echo esc_html($subscription->email); ?></strong>
                                <div class="row-actions">
                                    <span class="delete">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=palmerita-file-list&action=delete&id=' . $subscription->id), 'delete_file_sub_' . $subscription->id); ?>" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this subscription?', 'palmerita-subscriptions'); ?>')">
                                            <?php _e('Delete', 'palmerita-subscriptions'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-date" data-colname="<?php _e('Date Subscribed', 'palmerita-subscriptions'); ?>">
                                <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $subscription->date_created)); ?>
                            </td>
                            <td class="column-ip" data-colname="<?php _e('IP Address', 'palmerita-subscriptions'); ?>">
                                <?php echo esc_html($subscription->ip_address); ?>
                            </td>
                            <td class="column-downloads" data-colname="<?php _e('Downloads', 'palmerita-subscriptions'); ?>">
                                <span class="download-count <?php echo $download_count > 0 ? 'has-downloads' : 'no-downloads'; ?>">
                                    <?php echo esc_html($download_count); ?> <?php echo $download_count === 1 ? __('download', 'palmerita-subscriptions') : __('downloads', 'palmerita-subscriptions'); ?>
                                </span>
                            </td>
                            <td class="column-actions" data-colname="<?php _e('Actions', 'palmerita-subscriptions'); ?>">
                                <a href="mailto:<?php echo esc_attr($subscription->email); ?>" class="button button-small">
                                    <?php _e('Email', 'palmerita-subscriptions'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(__('%d items', 'palmerita-subscriptions'), $total_items); ?>
                </span>
                
                <?php
                $pagination_args = array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'add_args' => array('page' => 'palmerita-file-list')
                );
                
                if (!empty($search)) {
                    $pagination_args['add_args']['s'] = $search;
                }
                
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.palmerita-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.palmerita-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.palmerita-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 32px;
    color: #f59e0b;
    font-weight: bold;
}

.palmerita-empty-state {
    text-align: center;
    color: #666;
}

.palmerita-empty-state h3 {
    color: #333;
    margin-bottom: 10px;
}

.download-count.has-downloads {
    color: #46b450;
    font-weight: bold;
}

.download-count.no-downloads {
    color: #999;
}

.type-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    color: white;
}

.type-badge.plugin {
    background-color: #f59e0b;
}

@media (max-width: 782px) {
    .palmerita-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style> 