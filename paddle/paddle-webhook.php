<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_handle_webhook() {
    $webhook_secret = wpwa_paddle_get_webhook_secret();
    
    if (empty($webhook_secret)) {
        wpwa_paddle_log('Webhook secret not configured');
        http_response_code(500);
        exit;
    }
    
    $payload = @file_get_contents('php://input');
    $signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';
    
    if (!wpwa_paddle_verify_webhook_signature($payload, $signature, $webhook_secret)) {
        wpwa_paddle_log('Webhook signature verification failed');
        http_response_code(401);
        exit;
    }
    
    $event = json_decode($payload, true);
    
    if (!$event) {
        wpwa_paddle_log('Invalid webhook payload');
        http_response_code(400);
        exit;
    }
    
    wpwa_paddle_log_webhook($event);
    
    $result = wpwa_paddle_process_webhook_event($event);
    
    wpwa_paddle_mark_webhook_processed($event['event_id'] ?? null, $result);
    
    http_response_code(200);
    echo json_encode(array('received' => true));
}

function wpwa_paddle_verify_webhook_signature($payload, $signature, $secret) {
    if (empty($signature)) {
        return false;
    }
    
    $parts = explode(';', $signature);
    $signature_parts = array();
    
    foreach ($parts as $part) {
        list($key, $value) = explode('=', $part, 2);
        $signature_parts[trim($key)] = trim($value);
    }
    
    if (!isset($signature_parts['ts']) || !isset($signature_parts['h1'])) {
        return false;
    }
    
    $timestamp = $signature_parts['ts'];
    $received_signature = $signature_parts['h1'];
    
    $signed_payload = $timestamp . ':' . $payload;
    $expected_signature = hash_hmac('sha256', $signed_payload, $secret);
    
    return hash_equals($expected_signature, $received_signature);
}

function wpwa_paddle_log_webhook($event) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_paddle_webhook_log';
    
    $wpdb->insert($table, array(
        'event_id' => $event['event_id'] ?? uniqid('paddle_'),
        'event_type' => $event['event_type'] ?? 'unknown',
        'payload' => json_encode($event),
        'processed' => 0
    ));
}

function wpwa_paddle_mark_webhook_processed($event_id, $result) {
    global $wpdb;
    
    if (!$event_id) return;
    
    $table = $wpdb->prefix . 'wpwa_paddle_webhook_log';
    
    $wpdb->update(
        $table,
        array(
            'processed' => 1,
            'error' => $result['success'] ? null : $result['message']
        ),
        array('event_id' => $event_id)
    );
}

function wpwa_paddle_process_webhook_event($event) {
    $event_type = $event['event_type'] ?? '';
    $data = $event['data'] ?? array();
    
    switch ($event_type) {
        case 'transaction.completed':
            return wpwa_paddle_handle_transaction_completed($data);
        
        case 'subscription.created':
            return wpwa_paddle_handle_subscription_created($data);
        
        case 'subscription.updated':
            return wpwa_paddle_handle_subscription_updated($data);
        
        case 'subscription.activated':
            return wpwa_paddle_handle_subscription_activated($data);
        
        case 'subscription.trialing':
            return wpwa_paddle_handle_subscription_trialing($data);
        
        case 'subscription.canceled':
            return wpwa_paddle_handle_subscription_canceled($data);
        
        case 'subscription.past_due':
            return wpwa_paddle_handle_subscription_past_due($data);
        
        default:
            wpwa_paddle_log('Unhandled webhook event: ' . $event_type);
            return array('success' => true, 'message' => 'Event not handled');
    }
}

function wpwa_paddle_handle_subscription_trialing($subscription) {
    global $wpdb;
    
    $custom_data = $subscription['custom_data'] ?? array();
    $weebly_user_id = $custom_data['weebly_user_id'] ?? '';
    
    if (empty($weebly_user_id)) {
        return array('success' => false, 'message' => 'Missing Weebly User ID');
    }

    $sub_table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
    
    $wpdb->update(
        $sub_table,
        array(
            'status' => 'trialing',
            'current_period_start' => date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['starts_at'])),
            'current_period_end'   => date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['ends_at'])),
        ),
        array('paddle_subscription_id' => $subscription['id'])
    );

    wpwa_paddle_send_trialing_email_by_sub_id($subscription['id']);
    return array('success' => true);
}

function wpwa_paddle_update_transaction_by_paddle_id($paddle_id, $data) {
    global $wpdb;

    $table = $wpdb->prefix . 'wpwa_paddle_transactions';

    $wpdb->update(
        $table,
        $data,
        array('paddle_transaction_id' => $paddle_id)
    );
}

function wpwa_paddle_handle_transaction_completed($transaction) {
    $custom_data = $transaction['custom_data'] ?? array();

    if (!isset($custom_data['app_source']) || $custom_data['app_source'] !== 'weebly_licenses') {
        return array('success' => true, 'message' => 'Ignored: Different app source');
    }

    // Check if this is a whitelist purchase
    if (isset($custom_data['purchase_type']) && $custom_data['purchase_type'] === 'whitelist') {
        return wpwa_paddle_handle_whitelist_purchase($transaction, $custom_data);
    }

    if (empty($custom_data['weebly_user_id']) || empty($custom_data['product_id'])) {
        return array('success' => false, 'message' => 'Missing custom data');
    }

    $existing = wpwa_paddle_get_transaction_by_paddle_id($transaction['id']);

    $data = array(
        'transaction_type' => isset($transaction['subscription_id']) ? 'subscription_initial' : 'one_time',
        'paddle_transaction_id' => $transaction['id'],
        'paddle_subscription_id' => $transaction['subscription_id'] ?? null,
        'paddle_customer_id' => $transaction['customer_id'],
        'weebly_user_id' => $custom_data['weebly_user_id'],
        'weebly_site_id' => $custom_data['weebly_site_id'] ?? null,
        'product_id' => $custom_data['product_id'],
        'final_url' => $custom_data['final_url'] ?? '',
        'amount' => floatval($transaction['details']['totals']['total']) / 100,
        'currency' => $transaction['currency_code'],
        'status' => 'completed',
        'access_token' => $custom_data['access_token'] ?? null,
        'metadata' => json_encode($custom_data)
    );

    if ($existing && $existing['status'] === 'completed') {
        wpwa_paddle_log('Skipping already completed transaction', $transaction['id']);
        return ['success' => true, 'message' => 'Already processed'];
    } else {
        wpwa_paddle_log('Creating new transaction from webhook', $transaction['id']);
        $transaction_id = wpwa_paddle_create_transaction($data);
    }

    if ($transaction['customer_id'] && $custom_data['weebly_user_id']) {
        wpwa_paddle_sync_customer_from_paddle(
            $transaction['customer_id'],
            $custom_data['weebly_user_id']
        );
    }

    if ($transaction_id) {
        wpwa_paddle_send_confirmation_email($transaction_id);
    }

    return array('success' => true, 'transaction_id' => $transaction_id);
}

function wpwa_paddle_handle_whitelist_purchase($transaction, $custom_data) {
    wpwa_paddle_log('Processing whitelist purchase', $custom_data);
    
    $weebly_user_id = $custom_data['weebly_user_id'] ?? '';
    $target_product_id = $custom_data['target_product_id'] ?? 0;
    
    if (empty($weebly_user_id) || empty($target_product_id)) {
        return array('success' => false, 'message' => 'Missing whitelist data');
    }
    
    // Create whitelist entry
    $whitelist_id = wpwa_paddle_create_whitelist_entry(array(
        'weebly_user_id' => $weebly_user_id,
        'product_id' => $target_product_id,
        'granted_by' => 'paddle_purchase',
        'reason' => 'Purchased via Paddle - Transaction: ' . $transaction['id'],
        'expiry_date' => null, // Permanent access
        'status' => 'active'
    ));
    
    if (!$whitelist_id) {
        return array('success' => false, 'message' => 'Failed to create whitelist entry');
    }
    
    // Also create a transaction record for reporting
    wpwa_paddle_create_transaction(array(
        'transaction_type' => 'whitelist_purchase',
        'paddle_transaction_id' => $transaction['id'],
        'paddle_customer_id' => $transaction['customer_id'],
        'weebly_user_id' => $weebly_user_id,
        'weebly_site_id' => $custom_data['weebly_site_id'] ?? null,
        'product_id' => $target_product_id,
        'amount' => floatval($transaction['details']['totals']['total']) / 100,
        'currency' => $transaction['currency_code'],
        'status' => 'completed',
        'metadata' => json_encode(array_merge($custom_data, array(
            'whitelist_id' => $whitelist_id,
            'purchase_type' => 'whitelist'
        )))
    ));
    
    wpwa_paddle_log('Whitelist entry created successfully', array(
        'whitelist_id' => $whitelist_id,
        'user_id' => $weebly_user_id,
        'product_id' => $target_product_id
    ));
    
    return array('success' => true, 'whitelist_id' => $whitelist_id);
}

function wpwa_paddle_handle_subscription_created($subscription) {
    $custom_data = $subscription['custom_data'] ?? array();
    
    if (!isset($custom_data['app_source']) || $custom_data['app_source'] !== 'weebly_licenses') {
        return array('success' => true, 'message' => 'Ignored');
    }
    
    if (empty($custom_data)) {
        return array('success' => false, 'message' => 'Missing custom data');
    }
    
    $current_period_start = isset($subscription['current_billing_period']['starts_at']) 
        ? date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['starts_at']))
        : current_time('mysql');
    
    $current_period_end = isset($subscription['current_billing_period']['ends_at'])
        ? date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['ends_at']))
        : date('Y-m-d H:i:s', strtotime('+1 year'));
    
    wpwa_paddle_create_subscription_record(array(
        'paddle_subscription_id' => $subscription['id'],
        'paddle_customer_id' => $subscription['customer_id'],
        'weebly_user_id' => $custom_data['weebly_user_id'] ?? '',
        'weebly_site_id' => $custom_data['weebly_site_id'] ?? null,
        'product_id' => $custom_data['product_id'] ?? 0,
        'paddle_price_id' => $subscription['items'][0]['price']['id'] ?? '',
        'status' => $subscription['status'],
        'current_period_start' => $current_period_start,
        'current_period_end' => $current_period_end,
        'scheduled_change' => $subscription['scheduled_change']['action'] ?? null,
        'access_token' => $custom_data['access_token'] ?? null,
        'metadata' => $custom_data
    ));
    
    return array('success' => true);
}

function wpwa_paddle_handle_subscription_updated($subscription) {
    $custom_data = $subscription['custom_data'] ?? array();
    
    if (!isset($custom_data['app_source']) || $custom_data['app_source'] !== 'weebly_licenses') {
        return array('success' => true, 'message' => 'Ignored');
    }
    
    $current_period_start = isset($subscription['current_billing_period']['starts_at'])
        ? date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['starts_at']))
        : current_time('mysql');
    
    $current_period_end = isset($subscription['current_billing_period']['ends_at'])
        ? date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['ends_at']))
        : date('Y-m-d H:i:s', strtotime('+1 year'));
    
    wpwa_paddle_update_subscription_record($subscription['id'], array(
        'status' => $subscription['status'],
        'current_period_start' => $current_period_start,
        'current_period_end' => $current_period_end,
        'scheduled_change' => $subscription['scheduled_change']['action'] ?? null
    ));
    
    return array('success' => true);
}

function wpwa_paddle_handle_subscription_activated($subscription) {
    return wpwa_paddle_handle_subscription_updated($subscription);
}

function wpwa_paddle_handle_subscription_canceled($subscription) {
    wpwa_paddle_update_subscription_record($subscription['id'], array(
        'status' => 'canceled'
    ));
    
    return array('success' => true);
}

function wpwa_paddle_handle_subscription_past_due($subscription) {
    wpwa_paddle_update_subscription_record($subscription['id'], array(
        'status' => 'past_due'
    ));
    
    return array('success' => true);
}

function wpwa_paddle_notify_weebly($transaction_id) {
    $transaction = wpwa_paddle_get_transaction($transaction_id);
    
    if (!$transaction || !empty($transaction['weebly_notified'])) {
        return false;
    }
    
    $product = wpwa_paddle_get_product($transaction['product_id']);
    if (!$product) {
        return false;
    }
    
    $access_token = trim(wpwa_paddle_decrypt_token($transaction['access_token']));
    
    if (empty($access_token)) {
        wpwa_paddle_log('Cannot notify Weebly: missing access token', array(
            'transaction_id' => $transaction_id
        ));
        return false;
    }
    
    $gross = round((float)$transaction['amount'], 2);
    $paddle_fee = round($gross * 0.05, 2);
    $net = round($gross - $paddle_fee, 2);
    $weebly_payout = round($net * 0.30, 2);
    
    $currency = strtoupper($transaction['currency'] ?: 'USD');
    
    $payload = array(
        'name' => $product['name'] . ' Purchase',
        'method' => 'purchase',
        'kind' => 'single',
        'term' => 'forever',
        'gross_amount' => $gross,
        'payable_amount' => $weebly_payout,
        'currency' => $currency
    );
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.weebly.com/v1/admin/app/payment_notifications",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "x-weebly-access-token: " . $access_token
        )
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_errno($curl)) {
        wpwa_paddle_log('Weebly cURL error', array('error' => curl_error($curl)));
        curl_close($curl);
        return false;
    }
    
    curl_close($curl);
    
    if ($http_code == 200) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_transactions';
        
        $wpdb->update(
            $table,
            array('weebly_notified' => 1),
            array('id' => $transaction_id),
            array('%d'),
            array('%d')
        );
        
        return true;
    }
    
    wpwa_paddle_log('Weebly notification error', array(
        'http_code' => $http_code,
        'response' => $response
    ));
    
    return false;
}

function wpwa_paddle_remove_access($transaction_id) {
    $transaction = wpwa_paddle_get_transaction($transaction_id);   
    if (!$transaction) {
        return false;
    }
    $product = wpwa_paddle_get_product($transaction['product_id']);
    if (!$product) {
        return false;
    }
    // Retrieve required data
    $site_id = $transaction['weebly_site_id'];
    $app_id = $product['client_id'];
    $access_token = trim(wpwa_paddle_decrypt_token($transaction['access_token']));
    
    if (!$site_id || !$app_id || !$access_token) {
        wpwa_paddle_log('Missing parameters for deauthorization', [
            'site_id' => $site_id,
            'app_id' => $app_id,
            'has_token' => !empty($access_token)
        ]);
        return false;
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.weebly.com/v1/user/sites/{$site_id}/apps/{$app_id}/deauthorize",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'site_id' => $site_id,
            'platform_app_id' => $app_id
        ]),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "x-weebly-access-token: " . $access_token
        ),
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code == 200) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'wpwa_paddle_transactions',
            array('status' => 'revoked'),
            array('id' => $transaction_id)
        );
        return true;
    }
    
    wpwa_paddle_log('Weebly Deauthorize Error', [
        'code' => $http_code,
        'response' => $response
    ]);
    
    return false;
}