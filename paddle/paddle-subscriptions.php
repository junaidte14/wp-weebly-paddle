<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_create_subscription_record($subscription_data) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    $data = array(
        'paddle_subscription_id' => $subscription_data['paddle_subscription_id'],
        'paddle_customer_id' => $subscription_data['paddle_customer_id'],
        'weebly_user_id' => $subscription_data['weebly_user_id'],
        'weebly_site_id' => $subscription_data['weebly_site_id'] ?? null,
        'product_id' => $subscription_data['product_id'],
        'paddle_price_id' => $subscription_data['paddle_price_id'],
        'status' => $subscription_data['status'],
        'current_period_start' => $subscription_data['current_period_start'],
        'current_period_end' => $subscription_data['current_period_end'],
        'scheduled_change' => $subscription_data['scheduled_change'] ?? null,
        'access_token' => $subscription_data['access_token'] ?? null,
        'metadata' => json_encode($subscription_data['metadata'] ?? array())
    );
    
    $wpdb->insert($table, $data);
    
    return $wpdb->insert_id;
}

function wpwa_paddle_update_subscription_record($paddle_subscription_id, $updates) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    return $wpdb->update(
        $table,
        $updates,
        array('paddle_subscription_id' => $paddle_subscription_id)
    );
}

function wpwa_paddle_get_subscription_by_paddle_id($paddle_subscription_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE paddle_subscription_id = %s",
        $paddle_subscription_id
    ), ARRAY_A);
}

function wpwa_paddle_get_user_subscriptions($weebly_user_id, $status = 'active') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    if ($status) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE weebly_user_id = %s AND status = %s ORDER BY created_at DESC",
            $weebly_user_id,
            $status
        ), ARRAY_A);
    }
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s ORDER BY created_at DESC",
        $weebly_user_id
    ), ARRAY_A);
}

function wpwa_paddle_cancel_subscription($paddle_subscription_id, $effective_from = 'next_billing_period') {
    try {
        $data = array(
            'effective_from' => $effective_from
        );
        
        $result = wpwa_paddle_api_request('/subscriptions/' . $paddle_subscription_id . '/cancel', 'POST', $data);
        
        if (!$result['success']) {
            return false;
        }
        
        wpwa_paddle_update_subscription_record($paddle_subscription_id, array(
            'scheduled_change' => 'cancel',
            'status' => $result['data']['status']
        ));
        
        return true;
        
    } catch (Exception $e) {
        wpwa_paddle_log('Subscription cancel error: ' . $e->getMessage());
        return false;
    }
}

function wpwa_paddle_reactivate_subscription($paddle_subscription_id) {
    try {
        $result = wpwa_paddle_api_request('/subscriptions/' . $paddle_subscription_id . '/resume', 'POST', array());
        
        if (!$result['success']) {
            return false;
        }
        
        wpwa_paddle_update_subscription_record($paddle_subscription_id, array(
            'scheduled_change' => null,
            'status' => 'active'
        ));
        
        return true;
        
    } catch (Exception $e) {
        wpwa_paddle_log('Subscription reactivate error: ' . $e->getMessage());
        return false;
    }
}

function wpwa_paddle_get_active_subscriptions_count() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE status = 'active'");
}

function wpwa_paddle_get_expiring_subscriptions($days = 7) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    $date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE status = 'active' 
         AND current_period_end <= %s 
         AND scheduled_change IS NULL
         ORDER BY current_period_end ASC",
        $date
    ), ARRAY_A);
}

function wpwa_paddle_user_has_active_subscription($weebly_user_id, $product_id, $site_id = '') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND weebly_site_id = %s
         AND product_id = %d 
         AND status = 'active' 
         AND current_period_end > NOW()",
        $weebly_user_id,
        $site_id,
        $product_id
    ));
    
    return $count > 0;
}