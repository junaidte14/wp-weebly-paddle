<?php
if (!defined('ABSPATH')) exit;

/**
 * ========================================
 * AJAX: Fetch Weebly Sites
 * ========================================
 */
add_action('wp_ajax_wpwa_get_weebly_sites', 'wpwa_get_weebly_sites');

function wpwa_get_weebly_sites() {

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $access_token = isset($_POST['access_token']) ? sanitize_text_field($_POST['access_token']) : '';

    if (empty($access_token)) {
        wp_send_json_error('Missing token');
    }

    // Decrypt token
    $access_token = wpwa_paddle_decrypt_token($access_token);

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.weebly.com/v1/user/sites",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . trim($access_token)
        )
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (curl_errno($curl)) {
        curl_close($curl);
        wp_send_json_error('cURL error');
    }

    curl_close($curl);

    $data = json_decode($response, true);

    if ($http_code !== 200 || !is_array($data)) {
        wp_send_json_error('Invalid response');
    }

    wp_send_json_success($data);
}