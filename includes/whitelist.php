<?php
if (!defined('ABSPATH')) exit;

// Shortcode: [wpwa_paddle_whitelist_checkout]
add_shortcode('wpwa_paddle_whitelist_checkout', 'wpwa_paddle_whitelist_checkout_shortcode');

function wpwa_paddle_whitelist_checkout_shortcode($atts) {
    $atts = shortcode_atts(array(
        'button_text' => 'Purchase Whitelist Access',
        'success_url' => home_url('/whitelist-success/'),
        'title' => 'Get Unlimited Access'
    ), $atts);
    
    // Get Weebly user_id and site_id from URL
    $weebly_user_id = isset($_GET['user_id']) ? sanitize_text_field($_GET['user_id']) : 'not_provided';
    $weebly_site_id = isset($_GET['site_id']) ? sanitize_text_field($_GET['site_id']) : 'not_provided';
    $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
    
    /* if (empty($weebly_user_id)) {
        return '<div class="wpwa-paddle-error">Missing user_id parameter</div>';
    }
    
    if (empty($product_id)) {
        return '<div class="wpwa-paddle-error">Missing product_id parameter</div>';
    } */
    
    // Get whitelist product configuration
    $whitelist_product_id = wpwa_paddle_get_option('whitelist_product_id');
    $whitelist_price_id = wpwa_paddle_get_option('whitelist_price_id');
    
    if (empty($whitelist_product_id) || empty($whitelist_price_id)) {
        return '<div class="wpwa-paddle-error">Whitelist product not configured. Please contact admin.</div>';
    }
    
    // Check if user already has whitelist access
    $existing = wpwa_paddle_check_local_whitelist($weebly_user_id, $product_id, $weebly_site_id);
    if ($existing['has_access']) {
        return '<div class="wpwa-paddle-success">✅ You already have whitelist access for this product!</div>';
    }
    
    ob_start();
    ?>
    <div class="wpwa-paddle-whitelist-checkout" id="wpwa-paddle-whitelist-checkout">
        <div class="wpwa-whitelist-card">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <p>Get permanent access to or 17 Weebly apps for your Weebly site.</p>
            
            <div class="wpwa-whitelist-details">
                <div class="detail-row">
                    <span class="label">User ID:</span>
                    <span class="value"><code><?php echo esc_html($weebly_user_id); ?></code></span>
                </div>
                <?php if ($weebly_site_id): ?>
                <div class="detail-row">
                    <span class="label">Site ID:</span>
                    <span class="value"><code><?php echo esc_html($weebly_site_id); ?></code></span>
                </div>
                <?php endif; ?>
            </div>
            
            <button type="button" 
                    id="wpwa-paddle-whitelist-btn" 
                    class="wpwa-paddle-button"
                    data-user-id="<?php echo esc_attr($weebly_user_id); ?>"
                    data-site-id="<?php echo esc_attr($weebly_site_id); ?>"
                    data-product-id="<?php echo esc_attr($product_id); ?>"
                    data-price-id="<?php echo esc_attr($whitelist_price_id); ?>"
                    data-success-url="<?php echo esc_url($atts['success_url']); ?>">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
    </div>
    
    <style>
    .wpwa-paddle-whitelist-checkout {
        max-width: 600px;
        margin: 40px auto;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .wpwa-whitelist-card {
        background: #fff;
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        text-align: center;
    }
    .wpwa-whitelist-card h2 {
        margin: 0 0 10px;
        font-size: 28px;
        color: #1a202c;
    }
    .wpwa-whitelist-card > p {
        color: #718096;
        margin-bottom: 30px;
    }
    .wpwa-whitelist-details {
        background: #f7fafc;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        text-align: left;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 600;
        color: #4a5568;
    }
    .detail-row .value code {
        background: #edf2f7;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
    }
    .wpwa-paddle-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border: none;
        padding: 16px 40px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .wpwa-paddle-button:hover {
        transform: translateY(-2px);
    }
    .wpwa-paddle-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .wpwa-paddle-error {
        background: #fed7d7;
        color: #c53030;
        padding: 16px;
        border-radius: 8px;
        text-align: center;
        margin: 20px auto;
        max-width: 600px;
    }
    .wpwa-paddle-success {
        background: #c6f6d5;
        color: #22543d;
        padding: 16px;
        border-radius: 8px;
        text-align: center;
        margin: 20px auto;
        max-width: 600px;
        font-weight: 600;
    }
    </style>
    
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <script>
    (function() {
        const clientToken = '<?php echo esc_js(wpwa_paddle_get_client_token()); ?>';
        const isSandbox = <?php echo wpwa_paddle_is_sandbox_mode() ? 'true' : 'false'; ?>;
        
        if (!clientToken) {
            console.error('Paddle client token not configured');
            return;
        }
        
        Paddle.Initialize({
            token: clientToken,
            environment: isSandbox ? 'sandbox' : 'production'
        });
        
        const btn = document.getElementById('wpwa-paddle-whitelist-btn');
        
        if (btn) {
            btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.textContent = 'Loading...';
                
                const userId = btn.dataset.userId;
                const siteId = btn.dataset.siteId;
                const productId = btn.dataset.productId;
                const priceId = btn.dataset.priceId;
                const successUrl = btn.dataset.successUrl;
                
                Paddle.Checkout.open({
                    items: [{
                        priceId: priceId,
                        quantity: 1
                    }],
                    customData: {
                        weebly_user_id: userId,
                        weebly_site_id: siteId,
                        target_product_id: productId,
                        purchase_type: 'whitelist',
                        app_source: 'weebly_licenses'
                    },
                    settings: {
                        successUrl: successUrl + '?user_id=' + userId + '&site_id=' + siteId,
                        displayMode: 'overlay',
                        theme: 'light'
                    }
                });
                
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js($atts['button_text']); ?>';
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function wpwa_paddle_get_whitelist_entry($id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE id = %d",
        $id
    ), ARRAY_A);
}

function wpwa_paddle_create_whitelist_entry($data) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    $defaults = array(
        'weebly_user_id' => '',
        'product_id' => 0,
        'granted_by' => 'system',
        'reason' => '',
        'expiry_date' => null,
        'status' => 'active'
    );
    
    $data = wp_parse_args($data, $defaults);
    
    // Check if already exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND product_id = %d 
         AND status = 'active'",
        $data['weebly_user_id'],
        $data['product_id']
    ));
    
    if ($existing) {
        wpwa_paddle_log('Whitelist entry already exists', array(
            'user_id' => $data['weebly_user_id'],
            'product_id' => $data['product_id']
        ));
        return $existing->id;
    }
    
    $inserted = $wpdb->insert($table, array(
        'weebly_user_id' => $data['weebly_user_id'],
        'product_id' => $data['product_id'],
        'granted_by' => $data['granted_by'],
        'reason' => $data['reason'],
        'expiry_date' => $data['expiry_date'],
        'status' => $data['status']
    ));
    
    if ($inserted) {
        wpwa_paddle_log('Whitelist entry created', array(
            'id' => $wpdb->insert_id,
            'user_id' => $data['weebly_user_id'],
            'product_id' => $data['product_id']
        ));
        return $wpdb->insert_id;
    }
    
    return false;
}

function wpwa_paddle_update_whitelist_entry($id, $data) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    return $wpdb->update($table, $data, array('id' => $id));
}

function wpwa_paddle_delete_whitelist_entry($id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    return $wpdb->delete($table, array('id' => $id));
}

function wpwa_paddle_revoke_whitelist_entry($id) {
    return wpwa_paddle_update_whitelist_entry($id, array(
        'status' => 'revoked'
    ));
}

function wpwa_paddle_get_all_whitelist_entries($status = 'active', $limit = 100, $offset = 0) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    if ($status) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $status,
            $limit,
            $offset
        ), ARRAY_A);
    }
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    ), ARRAY_A);
}

function wpwa_paddle_get_whitelist_count($status = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    if ($status) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE status = %s",
            $status
        ));
    }
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

function wpwa_paddle_get_user_whitelist_products($weebly_user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND status = 'active'
         AND (expiry_date IS NULL OR expiry_date > NOW())",
        $weebly_user_id
    ), ARRAY_A);
}

// AJAX handler for manual whitelist creation
add_action('wp_ajax_wpwa_paddle_create_manual_whitelist', 'wpwa_paddle_ajax_create_manual_whitelist');
function wpwa_paddle_ajax_create_manual_whitelist() {
    check_ajax_referer('wpwa_paddle_whitelist', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    $weebly_user_id = sanitize_text_field($_POST['weebly_user_id']);
    $product_id = absint($_POST['product_id']);
    $reason = sanitize_textarea_field($_POST['reason']);
    $expiry_date = !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null;
    
    if (empty($weebly_user_id) || empty($product_id)) {
        wp_send_json_error(array('message' => 'User ID and Product ID are required'));
    }
    
    $current_user = wp_get_current_user();
    
    $id = wpwa_paddle_create_whitelist_entry(array(
        'weebly_user_id' => $weebly_user_id,
        'product_id' => $product_id,
        'granted_by' => $current_user->user_login,
        'reason' => $reason,
        'expiry_date' => $expiry_date,
        'status' => 'active'
    ));
    
    if ($id) {
        wp_send_json_success(array('id' => $id, 'message' => 'Whitelist entry created'));
    } else {
        wp_send_json_error(array('message' => 'Failed to create whitelist entry'));
    }
}

// AJAX handler for revoking whitelist
add_action('wp_ajax_wpwa_paddle_revoke_whitelist', 'wpwa_paddle_ajax_revoke_whitelist');
function wpwa_paddle_ajax_revoke_whitelist() {
    check_ajax_referer('wpwa_paddle_whitelist', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    $id = absint($_POST['id']);
    
    if (wpwa_paddle_revoke_whitelist_entry($id)) {
        wp_send_json_success(array('message' => 'Whitelist entry revoked'));
    } else {
        wp_send_json_error(array('message' => 'Failed to revoke'));
    }
}

// AJAX handler for deleting whitelist
add_action('wp_ajax_wpwa_paddle_delete_whitelist', 'wpwa_paddle_ajax_delete_whitelist');
function wpwa_paddle_ajax_delete_whitelist() {
    check_ajax_referer('wpwa_paddle_whitelist', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    $id = absint($_POST['id']);
    
    if (wpwa_paddle_delete_whitelist_entry($id)) {
        wp_send_json_success(array('message' => 'Whitelist entry deleted'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete'));
    }
}