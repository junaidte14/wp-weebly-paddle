<?php
if (!defined('ABSPATH')) exit;

function wpwa_universal_user_has_access($weebly_user_id, $product_id, $site_id = '') {
    
    // Priority 1: Check whitelist if available (could be from any plugin)
    $whitelist_check = wpwa_paddle_check_local_whitelist($weebly_user_id, $product_id, $site_id);
    if ($whitelist_check['has_access']) {
        return $whitelist_check;
    }
    
    // Priority 2: Active Paddle subscription
    $paddle_sub_check = wpwa_paddle_check_subscription_access($weebly_user_id, $product_id, $site_id);
    if ($paddle_sub_check['has_access']) {
        return $paddle_sub_check;
    }
    
    // Priority 3: Paddle one-time purchase
    $paddle_purchase_check = wpwa_paddle_check_purchase_access($weebly_user_id, $product_id, $site_id);
    if ($paddle_purchase_check['has_access']) {
        return $paddle_purchase_check;
    }
    
    // Priority 5: Check Legacy WooCommerce if tables exist
    $legacy_purchase_check = wpwa_paddle_check_legacy_wc_access($weebly_user_id, $product_id, $site_id);
    if ($legacy_purchase_check['has_access']) {
        return $legacy_purchase_check;
    }
    
    return array(
        'has_access' => false,
        'source' => null,
        'details' => array()
    );
}

function wpwa_paddle_check_local_whitelist($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_whitelist';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return array('has_access' => false);
    }
    
    $whitelist_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND product_id = %d 
         AND status = 'active'
         AND (expiry_date IS NULL OR expiry_date > NOW())",
        $weebly_user_id,
        $product_id
    ), ARRAY_A);
    
    if ($whitelist_entry) {
        return array(
            'has_access' => true,
            'source' => 'paddle_whitelist',
            'details' => array(
                'whitelist_id' => $whitelist_entry['id'],
                'granted_by' => $whitelist_entry['granted_by'],
                'expiry_date' => $whitelist_entry['expiry_date']
            )
        );
    }
    
    return array('has_access' => false);
}

function wpwa_paddle_check_subscription_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s
         AND weebly_site_id = %s 
         AND product_id = %d 
         AND status = 'active' 
         AND current_period_end > NOW()",
        $weebly_user_id,
        $site_id,
        $product_id
    ), ARRAY_A);
    
    if ($subscription) {
        return array(
            'has_access' => true,
            'source' => 'paddle_subscription',
            'details' => array(
                'subscription_id' => $subscription['paddle_subscription_id'],
                'current_period_end' => $subscription['current_period_end'],
                'status' => $subscription['status']
            )
        );
    }
    
    return array('has_access' => false);
}

function wpwa_paddle_check_purchase_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s
         AND weebly_site_id = %s 
         AND product_id = %d 
         AND transaction_type = 'one_time' 
         AND status = 'completed' 
         ORDER BY created_at DESC 
         LIMIT 1",
        $weebly_user_id,
        $site_id,
        $product_id
    ), ARRAY_A);
    
    if ($transaction) {
        return array(
            'has_access' => true,
            'source' => 'paddle_purchase',
            'details' => array(
                'transaction_id' => $transaction['id'],
                'purchase_date' => $transaction['created_at'],
                'amount' => $transaction['amount']
            )
        );
    }
    
    return array('has_access' => false);
}

function wpwa_paddle_check_legacy_wc_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    // Get old product ID from meta
    $old_pr_id = get_post_meta($product_id, '_wpwa_paddle_old_pr_id', true);
    
    if (!$old_pr_id) {
        return array('has_access' => false);
    }
    
    $table = $wpdb->prefix . 'wpwa_archived_orders';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return array('has_access' => false);
    }
    
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s
         AND weebly_site_id = %s 
         AND product_id = %d 
         AND status IN ('completed', 'processing') 
         ORDER BY order_date DESC 
         LIMIT 1",
        $weebly_user_id,
        $site_id,
        $old_pr_id
    ), ARRAY_A);
    
    if ($order) {
        return array(
            'has_access' => true,
            'source' => 'woocommerce_lifetime',
            'details' => array(
                'wc_order_id' => $order['wc_order_id'],
                'order_number' => $order['order_number'],
                'purchase_date' => $order['order_date'],
                'amount' => $order['amount'],
                'note' => 'Legacy WooCommerce purchase - lifetime access'
            )
        );
    }
    
    return array('has_access' => false);
}

function wpwa_paddle_update_user_access_token($weebly_user_id, $weebly_site_id, $product_id, $access_token, $source = '') {
    global $wpdb;
    
    $encrypted_token = wpwa_paddle_encrypt_token($access_token);
    
    switch ($source) {
        case 'paddle_subscription':
            $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
            $wpdb->update(
                $table,
                array('access_token' => $encrypted_token),
                array('weebly_user_id' => $weebly_user_id, 'weebly_site_id' => $weebly_site_id, 'product_id' => $product_id)
            );
            break;
            
        case 'paddle_purchase':
            $table = $wpdb->prefix . 'wpwa_paddle_transactions';
            $wpdb->update(
                $table,
                array('access_token' => $encrypted_token),
                array(
                    'weebly_user_id' => $weebly_user_id,
                    'weebly_site_id' => $weebly_site_id, 
                    'product_id' => $product_id,
                    'transaction_type' => 'one_time'
                ),
                array('%s'),
                array('%s', '%s', '%d', '%s')
            );
            break;
            
        case 'woocommerce_lifetime':
            $old_pr_id = get_post_meta($product_id, '_wpwa_paddle_old_pr_id', true);
            if ($old_pr_id) {
                $table = $wpdb->prefix . 'wpwa_archived_orders';
                // Check if table exists before updating
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
                    $wpdb->update(
                        $table,
                        array('access_token' => $encrypted_token),
                        array('weebly_user_id' => $weebly_user_id, 'weebly_site_id' => $weebly_site_id, 'product_id' => $old_pr_id)
                    );
                }
            }
            break;
            
        case 'paddle_whitelist':
            // Whitelist doesn't need token update
            break;
    }
    
    wpwa_paddle_log('Access token updated', array(
        'user_id' => $weebly_user_id,
        'site_id' => $weebly_site_id,
        'product_id' => $product_id,
        'source' => $source
    ));
}

function wpwa_paddle_log_access_grant($weebly_user_id, $weebly_site_id, $product_id, $source) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_access_log';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return;
    }
    
    $wpdb->insert($table, array(
        'weebly_user_id' => $weebly_user_id,
        'weebly_site_id' => $weebly_site_id,
        'product_id' => $product_id,
        'access_source' => $source,
        'granted_at' => current_time('mysql')
    ));
}

function wpwa_paddle_get_user_access_summary($weebly_user_id, $product_id, $site_id = '') {
    $access = wpwa_universal_user_has_access($weebly_user_id, $product_id, $site_id);
    
    if (!$access['has_access']) {
        return 'No Access';
    }
    
    $labels = array(
        'paddle_whitelist' => '🎁 Whitelisted (Paddle)',
        'paddle_subscription' => '🔄 Active Subscription (Paddle)',
        'paddle_purchase' => '💳 Purchased (Paddle)',
        'woocommerce_lifetime' => '⭐ Lifetime Access (Legacy WC)'
    );
    
    return $labels[$access['source']] ?? 'Unknown';
}