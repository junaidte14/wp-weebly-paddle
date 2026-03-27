<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_get_or_create_customer($weebly_user_id, $email, $name = '') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s",
        $weebly_user_id
    ), ARRAY_A);
    
    if ($customer) {
        return $customer['paddle_customer_id'];
    }
    
    try {
        $data = array(
            'email' => $email,
            'custom_data' => array(
                'weebly_user_id' => $weebly_user_id
            )
        );
        
        if (!empty($name)) {
            $data['name'] = $name;
        }
        
        $result = wpwa_paddle_api_request('/customers', 'POST', $data);
        
        if (!$result['success']) {
            wpwa_paddle_log('Customer creation failed: ' . $result['message']);
            return null;
        }
        
        $paddle_customer = $result['data'];
        
        $wpdb->insert($table, array(
            'paddle_customer_id' => $paddle_customer['id'],
            'weebly_user_id' => $weebly_user_id,
            'email' => $email,
            'name' => $name,
            'metadata' => json_encode(array(
                'created_via' => 'wpwa_paddle_plugin',
                'created_at' => current_time('mysql')
            ))
        ));
        
        return $paddle_customer['id'];
        
    } catch (Exception $e) {
        wpwa_paddle_log('Customer creation error: ' . $e->getMessage());
        return null;
    }
}

function wpwa_paddle_get_customer_by_weebly_id($weebly_user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s",
        $weebly_user_id
    ), ARRAY_A);
}

function wpwa_paddle_get_customer_by_paddle_id($paddle_customer_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE paddle_customer_id = %s",
        $paddle_customer_id
    ), ARRAY_A);
}

function wpwa_paddle_sync_customer_from_paddle($paddle_customer_id, $weebly_user_id = null) {
    global $wpdb;
    
    if (empty($paddle_customer_id)) {
        return null;
    }
    
    $table = $wpdb->prefix . 'wpwa_paddle_customers';
    
    $existing = wpwa_paddle_get_customer_by_paddle_id($paddle_customer_id);
    if ($existing) {
        return $existing['paddle_customer_id'];
    }
    
    try {
        $result = wpwa_paddle_api_request('/customers/' . $paddle_customer_id, 'GET');
        
        if (!$result['success']) {
            return null;
        }
        
        $paddle_customer = $result['data'];
        
        $email = $paddle_customer['email'] ?? '';
        $name = $paddle_customer['name'] ?? '';
        
        if (empty($weebly_user_id)) {
            $weebly_user_id = $paddle_customer['custom_data']['weebly_user_id'] ?? null;
        }
        
        if (empty($weebly_user_id)) {
            wpwa_paddle_log('Cannot sync customer: weebly_user_id missing', array(
                'paddle_customer_id' => $paddle_customer_id
            ));
            return null;
        }
        
        $wpdb->insert($table, array(
            'paddle_customer_id' => $paddle_customer_id,
            'weebly_user_id' => $weebly_user_id,
            'email' => $email,
            'name' => $name,
            'metadata' => json_encode(array(
                'synced_via' => 'webhook',
                'synced_at' => current_time('mysql')
            ))
        ));
        
        wpwa_paddle_log('Customer synced from Paddle', array(
            'paddle_customer_id' => $paddle_customer_id,
            'weebly_user_id' => $weebly_user_id
        ));
        
        return $paddle_customer_id;
        
    } catch (Exception $e) {
        wpwa_paddle_log('Customer sync error: ' . $e->getMessage());
        return null;
    }
}