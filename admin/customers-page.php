<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_render_customers_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-paddle'));
    }
    
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    $per_page = 25;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    $where = '1=1';
    $values = array();
    
    if ($search) {
        $where .= ' AND (weebly_user_id LIKE %s OR email LIKE %s OR name LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $values = array($search_term, $search_term, $search_term);
    }
    
    $query = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $values[] = $per_page;
    $values[] = $offset;
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    $customers = $wpdb->get_results($query, ARRAY_A);
    
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    $total_pages = ceil($total_count / $per_page);
    ?>
    <div class="wrap wpwa-paddle-wrap">
        <h1>
            <span class="dashicons dashicons-admin-users"></span>
            Paddle Customers
        </h1>
        
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Customers</div>
                    <div class="wpwa-stat-value"><?php echo number_format($total_count); ?></div>
                </div>
            </div>
        </div>
        
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-paddle-customers">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search...">
                <button type="submit" class="button">Search</button>
                <a href="?page=wpwa-paddle-customers" class="button">Reset</a>
            </form>
        </div>
        
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paddle Customer ID</th>
                        <th>Weebly User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">No customers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong>#<?php echo $customer['id']; ?></strong></td>
                            <td><small><code><?php echo substr($customer['paddle_customer_id'], 0, 20); ?>...</code></small></td>
                            <td><code><?php echo esc_html($customer['weebly_user_id']); ?></code></td>
                            <td><?php echo esc_html($customer['name']); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($customer['email']); ?>"><?php echo esc_html($customer['email']); ?></a></td>
                            <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
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
    <?php
}