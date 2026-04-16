<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_render_transactions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-paddle'));
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'notify_weebly' && isset($_GET['transaction_id'])) {
        $transaction_id = intval($_GET['transaction_id']);
        check_admin_referer('wpwa_paddle_notify_weebly_' . $transaction_id);
        
        $result = wpwa_paddle_notify_weebly($transaction_id);
        
        if ($result) {
            wp_redirect(add_query_arg('weebly_status', 'success', admin_url('admin.php?page=wpwa-paddle-transactions')));
        } else {
            wp_redirect(add_query_arg('weebly_status', 'error', admin_url('admin.php?page=wpwa-paddle-transactions')));
        }
        exit;
    }
    
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    $per_page = 25;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    $transactions = wpwa_paddle_get_transactions(array(
        'status' => $status_filter,
        'transaction_type' => $type_filter,
        'limit' => $per_page,
        'offset' => $offset
    ));
    
    if ($search) {
        $transactions = array_filter($transactions, function($t) use ($search) {
            return stripos($t['weebly_user_id'], $search) !== false;
        });
    }
    
    $total_count = wpwa_paddle_get_transaction_count($status_filter);
    $total_pages = ceil($total_count / $per_page);
    
    $total_revenue = wpwa_paddle_get_total_revenue('succeeded');
    $total_transactions = wpwa_paddle_get_transaction_count();
    $completed_count = wpwa_paddle_get_transaction_count('succeeded');
    
    if (isset($_GET['weebly_status'])) {
        if ($_GET['weebly_status'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>Weebly notified successfully.</p></div>';
        }
        if ($_GET['weebly_status'] === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to notify Weebly.</p></div>';
        }
    }
    ?>
    <div class="wrap wpwa-paddle-wrap">
        <h1>
            <span class="dashicons dashicons-money-alt"></span>
            Paddle Transactions
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
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Transactions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($total_transactions); ?></div>
                    <small style="color: #666;">Completed: <?php echo number_format($completed_count); ?></small>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Avg Transaction Value</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_paddle_format_price($completed_count > 0 ? $total_revenue / $completed_count : 0); ?></div>
                </div>
            </div>
        </div>
        
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-paddle-transactions">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>>Failed</option>
                </select>
                
                <select name="type">
                    <option value="">All Types</option>
                    <option value="one_time" <?php selected($type_filter, 'one_time'); ?>>One-time</option>
                    <option value="subscription_initial" <?php selected($type_filter, 'subscription_initial'); ?>>Subscription Initial</option>
                    <option value="subscription_renewal" <?php selected($type_filter, 'subscription_renewal'); ?>>Subscription Renewal</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by User ID...">
                
                <button type="submit" class="button">Filter</button>
                <a href="?page=wpwa-paddle-transactions" class="button">Reset</a>
            </form>
        </div>
        
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">No transactions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): 
                            $product = wpwa_paddle_get_product($transaction['product_id']);
                            $product_name = $product ? $product['name'] : 'Unknown Product';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $transaction['id']; ?></strong></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                            <td>
                                <?php 
                                $type_labels = array(
                                    'one_time' => '🛍️ One-time',
                                    'subscription_initial' => '🔄 Subscription',
                                    'subscription_renewal' => '🔄 Renewal'
                                );
                                echo $type_labels[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                                ?>
                            </td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td>
                                <code><?php echo esc_html($transaction['weebly_user_id']); ?></code>
                                <?php if ($transaction['weebly_site_id']): ?>
                                    <br><small>Site: <?php echo esc_html($transaction['weebly_site_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo wpwa_paddle_format_price($transaction['amount']); ?></strong></td>
                            <td>
                                <?php 
                                $status_badges = array(
                                    'completed' => '<span style="background: #e6fffa; color: #047481; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ Completed</span>',
                                    'succeeded' => '<span style="background: #e6fffa; color: #047481; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ Succeeded</span>',
                                    'pending' => '<span style="background: #fffbeb; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">⏳ Pending</span>',
                                    'failed' => '<span style="background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">❌ Failed</span>'
                                );
                                echo $status_badges[$transaction['status']] ?? esc_html($transaction['status']);
                                ?>
                            </td>
                            <td>
                                <?php
                                $notify_url = wp_nonce_url(
                                    admin_url('admin.php?page=wpwa-paddle-transactions&action=notify_weebly&transaction_id=' . $transaction['id']),
                                    'wpwa_paddle_notify_weebly_' . $transaction['id']
                                );
                                
                                if (!$transaction['weebly_notified']) {
                                    echo '<a href="' . esc_url($notify_url) . '" class="button button-small">Notify Weebly</a>';
                                } else {
                                    echo '<span style="color:green;font-weight:600;">✔ Notified</span>';
                                }
                                ?>

                                <br><br>

                                <button 
                                    class="button button-secondary wpwa-site-lookup-btn"
                                    data-user="<?php echo esc_attr($transaction['weebly_user_id']); ?>"
                                    data-site="<?php echo esc_attr($transaction['weebly_site_id']); ?>"
                                    data-access="<?php echo esc_attr($transaction['access_token']); ?>"
                                >
                                    🔍 Site Lookup
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="wpwa-pagination">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;',
                'total' => $total_pages,
                'current' => $page
            ));
            ?>
        </div>
        <?php endif; ?>

        <!-- Modal -->
        <style>
        .wpwa-modal-overlay {
            position: fixed;
            top:0; left:0;
            width:100%; height:100%;
            background: rgba(0,0,0,0.5);
            z-index:9998;
        }

        .wpwa-modal-content {
            position: fixed;
            top:50%; left:50%;
            transform: translate(-50%, -50%);
            background:#fff;
            padding:20px;
            width:600px;
            max-height:80vh;
            overflow:auto;
            border-radius:10px;
            z-index:9999;
        }

        .wpwa-close {
            float:right;
            font-size:22px;
            cursor:pointer;
        }

        .wpwa-site-item {
            padding:12px;
            border:1px solid #ddd;
            border-radius:8px;
            margin-bottom:10px;
        }

        .wpwa-site-item.match {
            border-color:#007cba;
            background:#e6f0ff;
        }
        </style>
        <div id="wpwa-site-modal" style="display:none;">
            <div class="wpwa-modal-overlay"></div>

            <div class="wpwa-modal-content">
                <span class="wpwa-close">&times;</span>

                <h2>Weebly Sites</h2>

                <div id="wpwa-site-loader" style="display:none;">Loading...</div>

                <div id="wpwa-site-results">
                    <p>No data yet.</p>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($){

            let modal = $('#wpwa-site-modal');

            // Open modal
            $('.wpwa-site-lookup-btn').on('click', function(){

                let userId = $(this).data('user');
                let siteId = $(this).data('site');
                let accessToken = $(this).data('access');

                modal.show();
                $('#wpwa-site-results').html('');
                $('#wpwa-site-loader').show();

                // Fetch sites
                $.post(ajaxurl, {
                    action: 'wpwa_get_weebly_sites',
                    access_token: accessToken
                }, function(response){

                    $('#wpwa-site-loader').hide();

                    if (!response.success) {
                        $('#wpwa-site-results').html('<p style="color:red;">Error loading sites</p>');
                        return;
                    }

                    let html = '';

                    response.data.forEach(site => {

                        let isMatch = site.site_id == siteId;

                        html += `
                            <div class="wpwa-site-item ${isMatch ? 'match' : ''}">
                                <strong>${site.site_title}</strong><br>
                                <small>${site.site_id}</small>
                                ${isMatch ? '<div style="color:green;font-weight:600;">✔ Matched Site</div>' : ''}
                            </div>
                        `;
                    });

                    $('#wpwa-site-results').html(html);
                });

            });

            // Close modal
            $('.wpwa-close, .wpwa-modal-overlay').on('click', function(){
                modal.hide();
            });

        });
        </script>
        <!-- modal done -->
    </div>
    <?php
}