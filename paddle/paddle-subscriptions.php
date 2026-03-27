<?php
if (!defined('ABSPATH')) exit;

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