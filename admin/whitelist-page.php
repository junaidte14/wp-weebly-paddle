<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_render_whitelist_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-paddle'));
    }
    
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    $per_page = 25;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    $whitelist_entries = wpwa_paddle_get_all_whitelist_entries($status_filter, $per_page, $offset);
    
    if ($search) {
        $whitelist_entries = array_filter($whitelist_entries, function($entry) use ($search) {
            return stripos($entry['weebly_user_id'], $search) !== false || 
                   stripos($entry['reason'], $search) !== false;
        });
    }
    
    $total_count = wpwa_paddle_get_whitelist_count($status_filter);
    $total_pages = ceil($total_count / $per_page);
    $active_count = wpwa_paddle_get_whitelist_count('active');
    $revoked_count = wpwa_paddle_get_whitelist_count('revoked');
    
    ?>
    <div class="wrap wpwa-paddle-wrap">
        <h1>
            <span class="dashicons dashicons-awards"></span>
            Whitelist Management
        </h1>
        
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Active Whitelist</div>
                    <div class="wpwa-stat-value"><?php echo number_format($active_count); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(231,76,60,0.1); color: #e74c3c;">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Revoked</div>
                    <div class="wpwa-stat-value"><?php echo number_format($revoked_count); ?></div>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <button type="button" class="button button-primary" id="wpwa-add-whitelist-btn">
                <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                Add Whitelist Entry
            </button>
        </div>
        
        <div id="wpwa-whitelist-form-modal" style="display:none;">
            <div class="wpwa-modal-overlay">
                <div class="wpwa-modal-content">
                    <h2>Add Whitelist Entry</h2>
                    <form id="wpwa-whitelist-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="weebly_user_id">Weebly User ID *</label></th>
                                <td>
                                    <input type="text" id="weebly_user_id" name="weebly_user_id" 
                                           class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="product_id">Product *</label></th>
                                <td>
                                    <select id="product_id" name="product_id" class="regular-text" required>
                                        <option value="">Select Product</option>
                                        <?php
                                        $products = wpwa_paddle_get_all_products();
                                        foreach ($products as $product) {
                                            echo '<option value="' . esc_attr($product['id']) . '">' . 
                                                 esc_html($product['name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="reason">Reason</label></th>
                                <td>
                                    <textarea id="reason" name="reason" class="large-text" rows="3"></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="expiry_date">Expiry Date (Optional)</label></th>
                                <td>
                                    <input type="date" id="expiry_date" name="expiry_date">
                                    <p class="description">Leave empty for permanent access</p>
                                </td>
                            </tr>
                        </table>
                        <div class="wpwa-modal-actions">
                            <button type="submit" class="button button-primary">Create Whitelist Entry</button>
                            <button type="button" class="button wpwa-modal-close">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-paddle-whitelist">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                    <option value="revoked" <?php selected($status_filter, 'revoked'); ?>>Revoked</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search...">
                
                <button type="submit" class="button">Filter</button>
                <a href="?page=wpwa-paddle-whitelist" class="button">Reset</a>
            </form>
        </div>
        
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Weebly User ID</th>
                        <th>Product</th>
                        <th>Granted By</th>
                        <th>Reason</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($whitelist_entries)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">No whitelist entries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($whitelist_entries as $entry): 
                            $product = wpwa_paddle_get_product($entry['product_id']);
                            $product_name = $product ? $product['name'] : 'Unknown Product';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $entry['id']; ?></strong></td>
                            <td><code><?php echo esc_html($entry['weebly_user_id']); ?></code></td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td><?php echo esc_html($entry['granted_by']); ?></td>
                            <td><?php echo esc_html($entry['reason'] ?: '—'); ?></td>
                            <td>
                                <?php 
                                if ($entry['expiry_date']) {
                                    echo date('M j, Y', strtotime($entry['expiry_date']));
                                } else {
                                    echo '<em>Never</em>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_badges = array(
                                    'active' => '<span style="background: #e6fffa; color: #047481; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ Active</span>',
                                    'revoked' => '<span style="background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">❌ Revoked</span>'
                                );
                                echo $status_badges[$entry['status']] ?? esc_html($entry['status']);
                                ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></td>
                            <td>
                                <?php if ($entry['status'] === 'active'): ?>
                                    <button type="button" class="button button-small wpwa-revoke-btn" 
                                            data-id="<?php echo $entry['id']; ?>">Revoke</button>
                                <?php endif; ?>
                                <button type="button" class="button button-small button-link-delete wpwa-delete-btn" 
                                        data-id="<?php echo $entry['id']; ?>">Delete</button>
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
    </div>
    
    <style>
    .wpwa-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100000;
    }
    .wpwa-modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .wpwa-modal-content h2 {
        margin-top: 0;
    }
    .wpwa-modal-actions {
        margin-top: 20px;
        text-align: right;
    }
    .wpwa-modal-actions .button {
        margin-left: 10px;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        const nonce = '<?php echo wp_create_nonce('wpwa_paddle_whitelist'); ?>';
        
        // Open modal
        $('#wpwa-add-whitelist-btn').on('click', function() {
            $('#wpwa-whitelist-form-modal').fadeIn();
        });
        
        // Close modal
        $('.wpwa-modal-close').on('click', function() {
            $('#wpwa-whitelist-form-modal').fadeOut();
            $('#wpwa-whitelist-form')[0].reset();
        });
        
        // Submit form
        $('#wpwa-whitelist-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'wpwa_paddle_create_manual_whitelist');
            formData.append('nonce', nonce);
            
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Creating...');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Whitelist entry created successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        submitBtn.prop('disabled', false).text('Create Whitelist Entry');
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    submitBtn.prop('disabled', false).text('Create Whitelist Entry');
                }
            });
        });
        
        // Revoke whitelist
        $('.wpwa-revoke-btn').on('click', function() {
            if (!confirm('Revoke this whitelist entry?')) return;
            
            const id = $(this).data('id');
            const btn = $(this);
            
            btn.prop('disabled', true).text('Revoking...');
            
            $.post(ajaxurl, {
                action: 'wpwa_paddle_revoke_whitelist',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    alert('Whitelist entry revoked!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    btn.prop('disabled', false).text('Revoke');
                }
            });
        });
        
        // Delete whitelist
        $('.wpwa-delete-btn').on('click', function() {
            if (!confirm('Permanently delete this whitelist entry?')) return;
            
            const id = $(this).data('id');
            const btn = $(this);
            
            btn.prop('disabled', true).text('Deleting...');
            
            $.post(ajaxurl, {
                action: 'wpwa_paddle_delete_whitelist',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    alert('Whitelist entry deleted!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    btn.prop('disabled', false).text('Delete');
                }
            });
        });
    });
    </script>
    <?php
}