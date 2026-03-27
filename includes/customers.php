<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_get_customer($weebly_user_id) {
    return wpwa_paddle_get_customer_by_weebly_id($weebly_user_id);
}

function wpwa_paddle_get_all_customers($limit = 100, $offset = 0) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    ), ARRAY_A);
}

function wpwa_paddle_get_customer_count() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

function wpwa_paddle_get_customer_lifetime_value($weebly_user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM `{$table}` WHERE weebly_user_id = %s AND status = 'completed'",
        $weebly_user_id
    ));
}

function wpwa_paddle_update_customer_email($weebly_user_id, $new_email) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    $customer = wpwa_paddle_get_customer_by_weebly_id($weebly_user_id);
    
    if (!$customer) {
        return false;
    }
    
    try {
        $result = wpwa_paddle_api_request('/customers/' . $customer['paddle_customer_id'], 'PATCH', array(
            'email' => $new_email
        ));
        
        if (!$result['success']) {
            wpwa_paddle_log('Customer email update error: ' . $result['message']);
        }
    } catch (Exception $e) {
        wpwa_paddle_log('Customer email update error: ' . $e->getMessage());
    }
    
    return $wpdb->update(
        $table,
        array('email' => $new_email),
        array('weebly_user_id' => $weebly_user_id)
    );
}