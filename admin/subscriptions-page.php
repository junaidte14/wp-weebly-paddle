<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_render_subscriptions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-paddle'));
    }
    
    if (isset($_GET['action']) && isset($_GET['subscription_id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_text_field($_GET['action']);
        $subscription_id = sanitize_text_field($_GET['subscription_id']);
        
        if ($action === 'cancel' && wp_verify_nonce($_GET['_wpnonce'], 'cancel_subscription_' . $subscription_id)) {
            $result = wpwa_paddle_cancel_subscription($subscription_id);
            if ($result) {
                echo '<div class="notice notice-success"><p>Subscription cancelled successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to cancel subscription.</p></div>';
            }
        }
    }
    
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    $where = array('1=1');
    $values = array();
    
    if ($status_filter) {
        $where[] = 'status = %s';
        $values[] = $status_filter;
    }
    
    if ($search) {
        $where[] = 'weebly_user_id LIKE %s';
        $values[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    $where_sql = implode(' AND ', $where);
    
    $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT 100";
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    $subscriptions = $wpdb->get_results($query, ARRAY_A);
    
    $active_count = wpwa_paddle_get_active_subscriptions_count();
    ?>
    <div class="wrap wpwa-paddle-wrap">
        <h1>
            <span class="dashicons dashicons-update"></span>
            Paddle Subscriptions
        </h1>
        
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Active Subscriptions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($active_count); ?></div>
                </div>
            </div>
        </div>
        
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-paddle-subscriptions">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                    <option value="past_due" <?php selected($status_filter, 'past_due'); ?>>Past Due</option>
                    <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>Canceled</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by User ID...">
                
                <button type="submit" class="button">Filter</button>
                <a href="?page=wpwa-paddle-subscriptions" class="button">Reset</a>
            </form>
        </div>
        
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th>Current Period</th>
                        <th>Next Billing</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">No subscriptions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $subscription): 
                            $product = wpwa_paddle_get_product($subscription['product_id']);
                            $product_name = $product ? $product['name'] : 'Unknown Product';
                        ?>
                        <tr>
                            <td><small><code><?php echo substr($subscription['paddle_subscription_id'], 0, 20); ?>...</code></small></td>
                            <td>
                                <code><?php echo esc_html($subscription['weebly_user_id']); ?></code>
                                <?php if ($subscription['weebly_site_id']): ?>
                                    <br><small>Site: <?php echo esc_html($subscription['weebly_site_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td>
                                <?php
                                $status_badges = array(
                                    'active' => '<span style="background: #e6fffa; color: #047481; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ Active</span>',
                                    'past_due' => '<span style="background: #fffbeb; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">⚠️ Past Due</span>',
                                    'canceled' => '<span style="background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">❌ Canceled</span>'
                                );
                                echo $status_badges[$subscription['status']] ?? esc_html($subscription['status']);
                                ?>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($subscription['current_period_start'])); ?>
                                <br>
                                <small>to <?php echo date('M j, Y', strtotime($subscription['current_period_end'])); ?></small>
                            </td>
                            <td>
                                <?php if ($subscription['status'] === 'active'): ?>
                                    <strong><?php echo date('M j, Y', strtotime($subscription['current_period_end'])); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($subscription['created_at'])); ?></td>
                            <td>
                                <?php if ($subscription['status'] === 'active' && !$subscription['scheduled_change']): ?>
                                    <a href="?page=wpwa-paddle-subscriptions&action=cancel&subscription_id=<?php echo $subscription['paddle_subscription_id']; ?>&_wpnonce=<?php echo wp_create_nonce('cancel_subscription_' . $subscription['paddle_subscription_id']); ?>" 
                                       class="button button-small"
                                       onclick="return confirm('Cancel this subscription?')">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}