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

function wpwa_paddle_get_active_subscriptions_count() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE status = 'active'");
}