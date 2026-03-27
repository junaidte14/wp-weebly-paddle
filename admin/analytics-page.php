<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_render_analytics_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-paddle'));
    }
    
    $total_revenue = wpwa_paddle_get_total_revenue('completed');
    $total_transactions = wpwa_paddle_get_transaction_count();
    $active_subscriptions = wpwa_paddle_get_active_subscriptions_count();
    $avg_value = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
    
    $recent_transactions = wpwa_paddle_get_transactions(array('limit' => 10));
    ?>
    <div class="wrap wpwa-paddle-wrap">
        <h1>
            <span class="dashicons dashicons-chart-area"></span>
            Paddle Analytics
        </h1>
        
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Revenue</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_paddle_format_price($total_revenue); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Transactions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($total_transactions); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Active Subscriptions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($active_subscriptions); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-tag"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Avg Transaction Value</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_paddle_format_price($avg_value); ?></div>
                </div>
            </div>
        </div>
        
        <h2>Recent Transactions</h2>
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_transactions)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">No transactions yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $transaction): 
                            $product = wpwa_paddle_get_product($transaction['product_id']);
                        ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></td>
                            <td><?php echo esc_html($product ? $product['name'] : 'Unknown'); ?></td>
                            <td><code><?php echo esc_html($transaction['weebly_user_id']); ?></code></td>
                            <td><strong><?php echo wpwa_paddle_format_price($transaction['amount']); ?></strong></td>
                            <td><?php echo esc_html($transaction['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}