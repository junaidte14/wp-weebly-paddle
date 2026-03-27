<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_create_transaction($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    $paddle_txn_id = $data['paddle_transaction_id'] ?? null;
    $paddle_sub_id = $data['paddle_subscription_id'] ?? null;
    
    $existing_id = null;
    if ($paddle_txn_id) {
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE paddle_transaction_id = %s",
            $paddle_txn_id
        ));
    }
    
    $insert_data = array(
        'transaction_type' => $data['transaction_type'],
        'paddle_transaction_id' => $paddle_txn_id,
        'paddle_subscription_id' => $paddle_sub_id,
        'paddle_customer_id' => $data['paddle_customer_id'],
        'weebly_user_id' => $data['weebly_user_id'],
        'weebly_site_id' => $data['weebly_site_id'] ?? null,
        'product_id' => $data['product_id'],
        'final_url' => $data['final_url'] ?? null,
        'amount' => $data['amount'],
        'currency' => strtoupper($data['currency']),
        'status' => $data['status'] ?? 'completed',
        'access_token' => $data['access_token'] ?? null,
        'metadata' => $data['metadata'] ?? null
    );
    
    if ($existing_id) {
        $wpdb->update($table, $insert_data, array('id' => $existing_id));
        return $existing_id;
    } else {
        $insert_data['created_at'] = current_time('mysql');
        $insert_data['weebly_notified'] = 0;
        $inserted = $wpdb->insert($table, $insert_data);
        return $inserted ? $wpdb->insert_id : false;
    }
}

function wpwa_paddle_get_transaction($transaction_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE id = %d",
        $transaction_id
    ), ARRAY_A);
}

function wpwa_paddle_get_transaction_by_paddle_id($paddle_transaction_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE paddle_transaction_id = %s",
        $paddle_transaction_id
    ), ARRAY_A);
}

function wpwa_paddle_get_transaction_by_subscription($paddle_subscription_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE paddle_subscription_id = %s 
         ORDER BY created_at DESC, id DESC 
         LIMIT 1",
        $paddle_subscription_id
    ), ARRAY_A);
}

function wpwa_paddle_get_user_transactions($weebly_user_id, $limit = 50) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s ORDER BY created_at DESC LIMIT %d",
        $weebly_user_id,
        $limit
    ), ARRAY_A);
}

function wpwa_paddle_get_transactions($args = array()) {
    global $wpdb;
    
    $defaults = array(
        'status' => null,
        'product_id' => null,
        'transaction_type' => null,
        'limit' => 50,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    $where = array('1=1');
    $values = array();
    
    if ($args['status']) {
        $where[] = 'status = %s';
        $values[] = $args['status'];
    }
    
    if ($args['product_id']) {
        $where[] = 'product_id = %d';
        $values[] = $args['product_id'];
    }
    
    if ($args['transaction_type']) {
        $where[] = 'transaction_type = %s';
        $values[] = $args['transaction_type'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
    $values[] = $args['limit'];
    $values[] = $args['offset'];
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    return $wpdb->get_results($query, ARRAY_A);
}

function wpwa_paddle_get_transaction_count($status = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    if ($status) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE status = %s",
            $status
        ));
    }
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

function wpwa_paddle_get_total_revenue($status = 'completed') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM `{$table}` WHERE status = %s",
        $status
    ));
    
    return (float) $total;
}