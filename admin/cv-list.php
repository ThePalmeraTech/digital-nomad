<?php
/**
 * CV Subscriptions List Page
 * Manage CV subscription requests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'palmerita_subscriptions';

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['subscription_ids'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'bulk_action_cv')) {
        $ids = array_map('intval', $_POST['subscription_ids']);
        if (!empty($ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($ids_placeholder)", ...$ids));
            echo '<div class="notice notice-success"><p>' . sprintf(__('%d subscriptions deleted.', 'palmerita-subscriptions'), count($ids)) . '</p></div>';
        }
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (wp_verify_nonce($_GET['_wpnonce'], 'export_cv')) {
        $results = $wpdb->get_results("SELECT * FROM $table_name WHERE type = 'cv' AND status = 'active' ORDER BY date_created DESC");
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cv-subscriptions-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'Subscription Date', 'IP Address']);
        
        foreach ($results as $row) {
            fputcsv($output, [
                $row->email,
                $row->date_created,
                $row->ip_address
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count
$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'cv' AND status = 'active'");
$total_pages = ceil($total_items / $per_page);

// Get subscriptions
$subscriptions = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_name 
    WHERE type = 'cv' AND status = 'active' 
    ORDER BY date_created DESC 
    LIMIT %d OFFSET %d
", $per_page, $offset));

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-media-document"></span>
        <?php _e('CV Requests', 'palmerita-subscriptions'); ?>
    </h1>
    
    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=palmerita-cv-list&export=csv'), 'export_cv'); ?>" class="page-title-action">
        <span class="dashicons dashicons-download"></span>
        <?php _e('Export CSV', 'palmerita-subscriptions'); ?>
    </a>
    
    <a href="<?php echo admin_url('admin.php?page=palmerita-subscriptions'); ?>" class="page-title-action">
        <?php _e('← Back to Dashboard', 'palmerita-subscriptions'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Statistics -->
    <div class="palmerita-stats-bar">
        <div class="stat-item">
            <strong><?php echo number_format($total_items); ?></strong>
            <span><?php _e('Total Requests', 'palmerita-subscriptions'); ?></span>
        </div>
        <div class="stat-item">
            <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'cv' AND DATE(date_created) = CURDATE()"); ?></strong>
            <span><?php _e('Today', 'palmerita-subscriptions'); ?></span>
        </div>
        <div class="stat-item">
            <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'cv' AND date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); ?></strong>
            <span><?php _e('This Week', 'palmerita-subscriptions'); ?></span>
        </div>
        <div class="stat-item">
            <strong><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE type = 'cv' AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)"); ?></strong>
            <span><?php _e('This Month', 'palmerita-subscriptions'); ?></span>
        </div>
    </div>

    <?php if ($subscriptions): ?>
        <form method="post" id="cv-subscriptions-form">
            <?php wp_nonce_field('bulk_action_cv'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'palmerita-subscriptions'); ?></option>
                        <option value="bulk_delete"><?php _e('Delete', 'palmerita-subscriptions'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'palmerita-subscriptions'); ?>">
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(__('%s items', 'palmerita-subscriptions'), number_format_i18n($total_items)); ?>
                    </span>
                    <?php if ($total_pages > 1): ?>
                        <span class="pagination-links">
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                                'type' => 'array'
                            ));
                            if ($page_links) {
                                echo implode("\n", $page_links);
                            }
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th scope="col" class="manage-column column-email column-primary">
                            <?php _e('Email', 'palmerita-subscriptions'); ?>
                        </th>
                        <th scope="col" class="manage-column column-date">
                            <?php _e('Subscription Date', 'palmerita-subscriptions'); ?>
                        </th>
                        <th scope="col" class="manage-column column-ip">
                            <?php _e('IP Address', 'palmerita-subscriptions'); ?>
                        </th>
                        <th scope="col" class="manage-column column-actions">
                            <?php _e('Actions', 'palmerita-subscriptions'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="subscription_ids[]" value="<?php echo esc_attr($subscription->id); ?>">
                            </th>
                            <td class="column-email column-primary">
                                <strong><?php echo esc_html($subscription->email); ?></strong>
                                <div class="row-actions">
                                    <span class="copy">
                                        <a href="#" onclick="copyToClipboard('<?php echo esc_js($subscription->email); ?>')"><?php _e('Copy', 'palmerita-subscriptions'); ?></a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-date">
                                <abbr title="<?php echo esc_attr($subscription->date_created); ?>">
                                    <?php echo human_time_diff(strtotime($subscription->date_created), current_time('timestamp')) . ' ' . __('ago', 'palmerita-subscriptions'); ?>
                                </abbr>
                                <br>
                                <small><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->date_created)); ?></small>
                            </td>
                            <td class="column-ip">
                                <code><?php echo esc_html($subscription->ip_address); ?></code>
                            </td>
                            <td class="column-actions">
                                <a href="mailto:<?php echo esc_attr($subscription->email); ?>" class="button button-small">
                                    <span class="dashicons dashicons-email-alt"></span>
                                    <?php _e('Send Email', 'palmerita-subscriptions'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="action2" id="bulk-action-selector-bottom">
                        <option value="-1"><?php _e('Bulk Actions', 'palmerita-subscriptions'); ?></option>
                        <option value="bulk_delete"><?php _e('Delete', 'palmerita-subscriptions'); ?></option>
                    </select>
                    <input type="submit" id="doaction2" class="button action" value="<?php _e('Apply', 'palmerita-subscriptions'); ?>">
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav-pages">
                        <span class="pagination-links">
                            <?php
                            if ($page_links) {
                                echo implode("\n", $page_links);
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    <?php else: ?>
        <div class="no-subscriptions">
            <div class="no-subscriptions-icon">📄</div>
            <h2><?php _e('No CV requests yet', 'palmerita-subscriptions'); ?></h2>
            <p><?php _e('When visitors request your CV, they will appear here.', 'palmerita-subscriptions'); ?></p>
            <p>
                <?php _e('Make sure to use the shortcode', 'palmerita-subscriptions'); ?> 
                <code>[palmerita_subscription_buttons]</code> 
                <?php _e('on your website.', 'palmerita-subscriptions'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
.palmerita-stats-bar {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    padding: 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-item strong {
    display: block;
    font-size: 1.5rem;
    color: #8b5cf6;
    margin-bottom: 5px;
}

.stat-item span {
    color: #64748b;
    font-size: 0.9rem;
}

.column-email {
    width: 35%;
}

.column-date {
    width: 25%;
}

.column-ip {
    width: 15%;
}

.column-actions {
    width: 25%;
}

.no-subscriptions {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin: 20px 0;
}

.no-subscriptions-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.no-subscriptions h2 {
    color: #1e293b;
    margin-bottom: 10px;
}

.no-subscriptions p {
    color: #64748b;
    margin-bottom: 10px;
}

.no-subscriptions code {
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
    color: #7c3aed;
}

.button-small .dashicons {
    font-size: 14px;
    line-height: 1;
    vertical-align: middle;
    margin-right: 4px;
}

@media (max-width: 768px) {
    .palmerita-stats-bar {
        flex-direction: column;
        gap: 10px;
    }
    
    .stat-item {
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle select all checkbox
    const selectAll = document.getElementById('cb-select-all-1');
    const checkboxes = document.querySelectorAll('input[name="subscription_ids[]"]');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }
    
    // Handle bulk actions
    const form = document.getElementById('cv-subscriptions-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const action = document.querySelector('select[name="action"]').value;
            const checkedBoxes = document.querySelectorAll('input[name="subscription_ids[]"]:checked');
            
            if (action === 'bulk_delete' && checkedBoxes.length > 0) {
                if (!confirm('Are you sure you want to delete the selected subscriptions?')) {
                    e.preventDefault();
                }
            } else if (action !== '-1' && checkedBoxes.length === 0) {
                alert('Please select at least one subscription.');
                e.preventDefault();
            }
        });
    }
});

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Create temporary notification
        const notification = document.createElement('div');
        notification.textContent = 'Email copiado al portapapeles';
        notification.style.cssText = `
            position: fixed;
            top: 50px;
            right: 20px;
            background: #4ade80;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            z-index: 9999;
            font-size: 14px;
        `;
        document.body.appendChild(notification);
        
        setTimeout(function() {
            document.body.removeChild(notification);
        }, 2000);
    });
}
</script> 