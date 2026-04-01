<?php
if (!defined('ABSPATH')) exit;

// Load dependencies safely
if ( ! class_exists( 'HMAC' ) ) {
    require_once WPWA_PADDLE_DIR . '/lib/Util/HMAC.php';
}

if ( ! class_exists( 'WeeblyClient' ) ) {
    require_once WPWA_PADDLE_DIR . '/lib/Weebly/WeeblyClient.php';
}

function wpwa_paddle_handle_phase_one() {
    wpwa_paddle_log("Phase One Started", $_GET);
    
    if (strpos($_SERVER['QUERY_STRING'], '?') !== false) {
        $fixed = str_replace('?', '&', $_SERVER['QUERY_STRING']);
        $redirect_to = strtok($_SERVER['REQUEST_URI'], '?') . '?' . $fixed;
        wp_redirect($redirect_to);
        exit;
    }
    
    $product_id = 0;
    if (isset($_GET['state'])) {
        $state_data = json_decode(base64_decode(rawurldecode($_GET['state'])), true);
        $product_id = isset($state_data['pr_id']) ? absint($state_data['pr_id']) : 0;
    }
    
    if (!$product_id && isset($_GET['pr_id'])) {
        $product_id = absint($_GET['pr_id']);
    }
    
    if (!$product_id) {
        wp_die('Missing product ID');
    }
    
    $product = wpwa_paddle_get_product($product_id);
    if (!$product) {
        wp_die('Invalid product');
    }
    
    if (isset($_GET['authorization_code'])) {
        wpwa_paddle_handle_oauth_callback($product);
    } else {
        wpwa_paddle_initiate_oauth($product);
    }
}

function wpwa_paddle_initiate_oauth($product) {
    $cid = $product['client_id'];
    $csec = $product['client_secret'];
    
    $hmac_params = array(
        'user_id' => $_GET['user_id'] ?? '',
        'timestamp' => $_GET['timestamp'] ?? ''
    );
    if (isset($_GET['site_id'])) $hmac_params['site_id'] = $_GET['site_id'];
    
    $hmac_valid = HMAC::isHmacValid(http_build_query($hmac_params), $csec, $_GET['hmac'] ?? '');
    
    if (!$hmac_valid) {
        wp_die('HMAC verification failed');
    }
    
    $state = rawurlencode(base64_encode(json_encode(array(
        'pr_id' => $product['pr_id'],
        'csrf' => wp_create_nonce('wpwa_paddle_oauth'),
    ))));
    
    $redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_one/?pr_id=' . $product['pr_id'];
    
    $auth_url = 'https://www.weebly.com/app-center/oauth/authorize?' . http_build_query(array(
        'client_id' => $cid,
        'user_id' => $_GET['user_id'],
        'site_id' => $_GET['site_id'] ?? '',
        'redirect_uri' => $redirect_uri,
        'state' => $state,
        'version' => '1.0.4'
    ), '', '&', PHP_QUERY_RFC3986);
    
    wp_redirect($auth_url);
    exit;
}

function wpwa_paddle_handle_oauth_callback($product) {
    $client_id = $product['client_id'];
    $client_secret = $product['client_secret'];
    
    $authorization_code = sanitize_text_field($_GET['authorization_code']);
    $user_id = sanitize_text_field($_GET['user_id'] ?? '');
    $site_id = sanitize_text_field($_GET['site_id'] ?? '');
    $callback_url = isset($_GET['callback_url']) ? esc_url_raw($_GET['callback_url']) : '';
    
    $weebly_client = new WeeblyClient($client_id, $client_secret, $user_id, $site_id);
    $token_response = $weebly_client->getAccessToken($authorization_code, $callback_url);
    
    if (empty($token_response->access_token)) {
        wp_die('Failed to obtain access token');
    }
    
    $access_token = $token_response->access_token;
    $final_url = $token_response->callback_url ?? $callback_url;
    
    $email = '';
    $name = '';
    
    // Universal access check across all payment processors
    $access_check = wpwa_universal_user_has_access($user_id, $product['id'], $site_id);
    
    if ($access_check['has_access']) {
        wpwa_paddle_log('Access granted - skipping payment', array(
            'user_id' => $user_id,
            'source' => $access_check['source']
        ));
        
        wpwa_paddle_update_user_access_token($user_id, $site_id, $product['id'], $access_token, $access_check['source']);
        
        add_filter('allowed_redirect_hosts', function($hosts) {
            $hosts[] = 'www.weebly.com';
            return $hosts;
        });
        
        wp_safe_redirect($final_url);
        exit;
    }
    
    $checkout_args = array(
        'product_id' => $product['id'],
        'weebly_user_id' => $user_id,
        'weebly_site_id' => $site_id,
        'access_token' => $access_token,
        'final_url' => $final_url,
        'email' => $email,
        'name' => $name,
        'pr_id' => $product['pr_id'] ?? $product['id']
    );
    
    $session_result = wpwa_paddle_create_checkout_transaction($checkout_args);
    
    if (!$session_result['success'] || empty($session_result['checkout_url'])) {
        wp_die('Failed to create checkout: ' . ($session_result['message'] ?? 'Unknown error'));
    }
    
    wp_redirect($session_result['checkout_url']);
    exit;
}
