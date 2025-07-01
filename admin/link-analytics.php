<?php
/**
 * Link Analytics Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$downloads_table = $wpdb->prefix . 'palmerita_downloads';
$subscriptions_table = $wpdb->prefix . 'palmerita_subscriptions';

// Handle individual actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($_GET['action'] === 'delete' && wp_verify_nonce($_GET['_wpnonce'], 'delete_link_' . $id)) {
        $wpdb->update(
            $downloads_table,
            array('status' => 'deleted'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             __('Link deleted successfully.', 'palmerita-subscriptions') . 
             '</p></div>';
    }
}

// Pagination and filtering
$items_per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filters
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$filter_clicked = isset($_GET['filter_clicked']) ? sanitize_text_field($_GET['filter_clicked']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Build query
$where_conditions = array("d.status != 'deleted'");
$query_params = array();

if (!empty($filter_type)) {
    $where_conditions[] = "s.type = %s";
    $query_params[] = $filter_type;
}

if (!empty($filter_status)) {
    $where_conditions[] = "d.status = %s";
    $query_params[] = $filter_status;
}

if (!empty($filter_clicked)) {
    if ($filter_clicked === 'clicked') {
        $where_conditions[] = "d.clicked = 1";
    } else {
        $where_conditions[] = "d.clicked = 0";
    }
}

if (!empty($search)) {
    $where_conditions[] = "d.email LIKE %s";
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get results with JOIN to subscriptions table
$query = "
    SELECT d.*, s.type as subscription_type 
    FROM $downloads_table d 
    LEFT JOIN $subscriptions_table s ON d.subscription_id = s.id 
    $where_clause 
    ORDER BY d.created DESC 
    LIMIT %d OFFSET %d
";

$query_params[] = $items_per_page;
$query_params[] = $offset;

$results = $wpdb->get_results($wpdb->prepare($query, $query_params));

// Get total count
$total_query = "
    SELECT COUNT(*) 
    FROM $downloads_table d 
    LEFT JOIN $subscriptions_table s ON d.subscription_id = s.id 
    $where_clause
";
$total_params = array_slice($query_params, 0, -2);
$total_items = $wpdb->get_var($wpdb->prepare($total_query, $total_params));

$total_pages = ceil($total_items / $items_per_page);

// Get statistics
$stats = array(
    'total_links' => $wpdb->get_var("SELECT COUNT(*) FROM $downloads_table WHERE status != 'deleted'"),
    'clicked_links' => $wpdb->get_var("SELECT COUNT(*) FROM $downloads_table WHERE clicked = 1 AND status != 'deleted'"),
    'total_clicks' => $wpdb->get_var("SELECT SUM(click_count) FROM $downloads_table WHERE status != 'deleted'"),
    'expired_links' => $wpdb->get_var("SELECT COUNT(*) FROM $downloads_table WHERE status = 'expired'"),
    'active_links' => $wpdb->get_var("SELECT COUNT(*) FROM $downloads_table WHERE status = 'active'"),
    'today_clicks' => $wpdb->get_var("SELECT SUM(click_count) FROM $downloads_table WHERE DATE(last_clicked) = CURDATE() AND status != 'deleted'")
);

// Calculate click-through rate
$click_rate = $stats['total_links'] > 0 ? round(($stats['clicked_links'] / $stats['total_links']) * 100, 1) : 0;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Link Analytics', 'palmerita-subscriptions'); ?>
        <span class="title-count">(<?php echo number_format($total_items); ?>)</span>
    </h1>
    
    <!-- Statistics Dashboard -->
    <div class="analytics-stats-grid" style="margin: 20px 0;">
        <div class="analytics-stat-card">
            <h3><?php _e('Total Links', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number">
                <strong><?php echo number_format($stats['total_links']); ?></strong>
            </div>
        </div>
        
        <div class="analytics-stat-card">
            <h3><?php _e('Clicked Links', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number clicked">
                <strong><?php echo number_format($stats['clicked_links']); ?></strong>
            </div>
            <div class="stat-meta"><?php echo $click_rate; ?>% click rate</div>
        </div>
        
        <div class="analytics-stat-card">
            <h3><?php _e('Total Clicks', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number">
                <strong><?php echo number_format($stats['total_clicks'] ?: 0); ?></strong>
            </div>
        </div>
        
        <div class="analytics-stat-card">
            <h3><?php _e('Today\'s Clicks', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number today">
                <strong><?php echo number_format($stats['today_clicks'] ?: 0); ?></strong>
            </div>
        </div>
        
        <div class="analytics-stat-card">
            <h3><?php _e('Active Links', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number active">
                <strong><?php echo number_format($stats['active_links']); ?></strong>
            </div>
        </div>
        
        <div class="analytics-stat-card">
            <h3><?php _e('Expired Links', 'palmerita-subscriptions'); ?></h3>
            <div class="stat-number expired">
                <strong><?php echo number_format($stats['expired_links']); ?></strong>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="palmerita-link-analytics">
            
            <select name="filter_type">
                <option value=""><?php _e('All Types', 'palmerita-subscriptions'); ?></option>
                <option value="cv" <?php selected($filter_type, 'cv'); ?>><?php _e('CV Downloads', 'palmerita-subscriptions'); ?></option>
                <option value="plugin" <?php selected($filter_type, 'plugin'); ?>><?php _e('File Downloads', 'palmerita-subscriptions'); ?></option>
            </select>
            
            <select name="filter_status">
                <option value=""><?php _e('All Status', 'palmerita-subscriptions'); ?></option>
                <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active', 'palmerita-subscriptions'); ?></option>
                <option value="expired" <?php selected($filter_status, 'expired'); ?>><?php _e('Expired', 'palmerita-subscriptions'); ?></option>
                <option value="exhausted" <?php selected($filter_status, 'exhausted'); ?>><?php _e('Download Limit Reached', 'palmerita-subscriptions'); ?></option>
            </select>
            
            <select name="filter_clicked">
                <option value=""><?php _e('All Links', 'palmerita-subscriptions'); ?></option>
                <option value="clicked" <?php selected($filter_clicked, 'clicked'); ?>><?php _e('Clicked', 'palmerita-subscriptions'); ?></option>
                <option value="unclicked" <?php selected($filter_clicked, 'unclicked'); ?>><?php _e('Never Clicked', 'palmerita-subscriptions'); ?></option>
            </select>
            
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search emails...', 'palmerita-subscriptions'); ?>">
            
            <input type="submit" class="button" value="<?php _e('Filter', 'palmerita-subscriptions'); ?>">
            
            <?php if ($filter_type || $filter_status || $filter_clicked || $search): ?>
                <a href="<?php echo admin_url('admin.php?page=palmerita-link-analytics'); ?>" class="button"><?php _e('Clear Filters', 'palmerita-subscriptions'); ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-email column-primary">
                    <?php _e('Email', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-type">
                    <?php _e('Type', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php _e('Status', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-created">
                    <?php _e('Created', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-expires">
                    <?php _e('Expires', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-clicked">
                    <?php _e('Clicked', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-clicks">
                    <?php _e('Clicks', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-last-click">
                    <?php _e('Last Click', 'palmerita-subscriptions'); ?>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php _e('Actions', 'palmerita-subscriptions'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($results)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <div class="analytics-empty-state">
                            <h3><?php _e('No link data found', 'palmerita-subscriptions'); ?></h3>
                            <p><?php _e('Link analytics will appear here when users receive download links.', 'palmerita-subscriptions'); ?></p>
                            <div style="margin-top: 20px;">
                                <span style="font-size: 48px;">📊</span>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($results as $link): ?>
                    <tr>
                        <td class="column-email column-primary" data-colname="<?php _e('Email', 'palmerita-subscriptions'); ?>">
                            <strong><?php echo esc_html($link->email); ?></strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url(site_url('/palmerita-track/' . $link->token)); ?>" target="_blank">
                                        <?php _e('View Link', 'palmerita-subscriptions'); ?>
                                    </a> |
                                </span>
                                <span class="delete">
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=palmerita-link-analytics&action=delete&id=' . $link->id), 'delete_link_' . $link->id); ?>" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this link?', 'palmerita-subscriptions'); ?>')">
                                        <?php _e('Delete', 'palmerita-subscriptions'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-type" data-colname="<?php _e('Type', 'palmerita-subscriptions'); ?>">
                            <span class="type-badge <?php echo esc_attr($link->subscription_type); ?>">
                                <?php echo $link->subscription_type === 'cv' ? 'CV' : ($link->subscription_type === 'plugin' ? 'File' : 'Other'); ?>
                            </span>
                        </td>
                        <td class="column-status" data-colname="<?php _e('Status', 'palmerita-subscriptions'); ?>">
                            <span class="status-badge <?php echo esc_attr($link->status); ?>">
                                <?php echo esc_html(ucfirst($link->status)); ?>
                            </span>
                        </td>
                        <td class="column-created" data-colname="<?php _e('Created', 'palmerita-subscriptions'); ?>">
                            <?php echo esc_html(mysql2date('M j, Y g:i a', $link->created)); ?>
                        </td>
                        <td class="column-expires" data-colname="<?php _e('Expires', 'palmerita-subscriptions'); ?>">
                            <?php 
                            $expires = mysql2date('M j, Y g:i a', $link->expires);
                            $is_expired = strtotime($link->expires) < current_time('timestamp');
                            echo $is_expired ? '<span style="color: #dc3232;">' . $expires . '</span>' : $expires;
                            ?>
                        </td>
                        <td class="column-clicked" data-colname="<?php _e('Clicked', 'palmerita-subscriptions'); ?>">
                            <?php if ($link->clicked): ?>
                                <span class="clicked-yes">✅ <?php _e('Yes', 'palmerita-subscriptions'); ?></span>
                                <div class="first-click-time">
                                    <?php echo esc_html(mysql2date('M j, g:i a', $link->first_clicked)); ?>
                                </div>
                            <?php else: ?>
                                <span class="clicked-no">❌ <?php _e('No', 'palmerita-subscriptions'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-clicks" data-colname="<?php _e('Clicks', 'palmerita-subscriptions'); ?>">
                            <strong><?php echo esc_html($link->click_count); ?></strong>
                        </td>
                        <td class="column-last-click" data-colname="<?php _e('Last Click', 'palmerita-subscriptions'); ?>">
                            <?php echo $link->last_clicked ? esc_html(mysql2date('M j, g:i a', $link->last_clicked)) : '—'; ?>
                        </td>
                        <td class="column-actions" data-colname="<?php _e('Actions', 'palmerita-subscriptions'); ?>">
                            <a href="mailto:<?php echo esc_attr($link->email); ?>" class="button button-small">
                                📧 <?php _e('Email', 'palmerita-subscriptions'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

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
                    'add_args' => array('page' => 'palmerita-link-analytics')
                );
                
                // Preserve filters in pagination
                if ($filter_type) $pagination_args['add_args']['filter_type'] = $filter_type;
                if ($filter_status) $pagination_args['add_args']['filter_status'] = $filter_status;
                if ($filter_clicked) $pagination_args['add_args']['filter_clicked'] = $filter_clicked;
                if ($search) $pagination_args['add_args']['s'] = $search;
                
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.analytics-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.analytics-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.analytics-stat-card h3 {
    margin: 0 0 8px 0;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 24px;
    color: #0073aa;
    font-weight: bold;
}

.stat-number.clicked {
    color: #46b450;
}

.stat-number.today {
    color: #f56500;
}

.stat-number.active {
    color: #00a32a;
}

.stat-number.expired {
    color: #dc3232;
}

.stat-meta {
    font-size: 11px;
    color: #666;
    margin-top: 4px;
}

.analytics-empty-state {
    text-align: center;
    color: #666;
}

.analytics-empty-state h3 {
    color: #333;
    margin-bottom: 10px;
}

.type-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: white;
}

.type-badge.cv {
    background-color: #6366f1;
}

.type-badge.plugin {
    background-color: #f59e0b;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: white;
}

.status-badge.active {
    background-color: #46b450;
}

.status-badge.expired {
    background-color: #dc3232;
}

.status-badge.exhausted {
    background-color: #f56500;
}

.clicked-yes {
    color: #46b450;
    font-weight: bold;
}

.clicked-no {
    color: #999;
}

.first-click-time {
    font-size: 11px;
    color: #666;
    margin-top: 2px;
}

.title-count {
    color: #666;
    font-weight: normal;
    font-size: 14px;
}

@media (max-width: 782px) {
    .analytics-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tablenav.top form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .tablenav.top form > * {
        margin-bottom: 5px;
    }
}
</style> 