<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_api_request($endpoint, $method = 'GET', $data = null) {
    $api_key = wpwa_paddle_get_api_key();
    
    if (empty($api_key)) {
        wpwa_paddle_log('API key not configured');
        return array('success' => false, 'message' => 'API key missing');
    }
    
    $base_url = wpwa_paddle_is_sandbox_mode() 
        ? 'https://sandbox-api.paddle.com' 
        : 'https://api.paddle.com';
    
    $url = $base_url . $endpoint;
    
    $args = array(
        'method' => $method,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    );
    
    if ($data && in_array($method, array('POST', 'PATCH'))) {
        $args['body'] = json_encode($data);
    }
    
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        wpwa_paddle_log('API request error: ' . $response->get_error_message());
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $body = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);
    
    $result = json_decode($body, true);
    
    if ($http_code >= 200 && $http_code < 300) {
        return array('success' => true, 'data' => $result['data'] ?? $result);
    }
    
    wpwa_paddle_log('API error', array('code' => $http_code, 'response' => $result));
    return array('success' => false, 'message' => $result['error']['detail'] ?? 'API request failed', 'code' => $http_code);
}

function wpwa_paddle_is_configured() {
    $sandbox_key = wpwa_paddle_get_option('sandbox_api_key');
    $live_key = wpwa_paddle_get_option('live_api_key');
    
    if (wpwa_paddle_is_sandbox_mode()) {
        return !empty($sandbox_key);
    }
    
    return !empty($live_key);
}

function wpwa_paddle_get_webhook_secret() {
    if (wpwa_paddle_is_sandbox_mode()) {
        return wpwa_paddle_get_option('sandbox_webhook_secret');
    }
    return wpwa_paddle_get_option('live_webhook_secret');
}