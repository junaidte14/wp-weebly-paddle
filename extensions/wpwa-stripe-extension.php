<?php

if (!defined('ABSPATH')) exit;

class WPWA_Stripe_Extension {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wpwa_paddle_admin_menu', array($this, 'add_settings_submenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_stripe_js'));
        
        // Access check hooks
        add_filter('wpwa_custom_gateway_access_check', array($this, 'check_stripe_access'), 10, 4);
        add_filter('wpwa_update_access_token', array($this, 'update_stripe_token'), 10, 6);
        add_filter('wpwa_access_source_labels', array($this, 'add_access_labels'));
        
        // Register webhook route
        add_filter('wpwa_paddle_custom_routes', array($this, 'register_routes'));

        // Inject Stripe button into the unified card
        add_action('wpwa_checkout_card_buttons', array($this, 'render_stripe_button'), 10, 2);
    }

    public function render_stripe_button($transaction, $product) {
        $url = $this->create_stripe_checkout_session($transaction); 
        ?>
        <a href="<?php echo esc_url($url); ?>" class="btn-paddle">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 4H4C2.89 4 2.01 4.89 2.01 6L2 18C2 19.11 2.89 20 4 20H20C21.11 20 22 19.11 22 18V6C22 4.89 21.11 4 20 4ZM20 18H4V12H20V18ZM20 8H4V6H20V8Z" fill="white"/>
            </svg>
            Pay by Credit Card (Stripe)
        </a>
        <div class="divider">OR</div>
        <?php
    }

    private function create_stripe_checkout_session($transaction) {
        try {
            $product = wpwa_paddle_get_product($transaction['product_id']);
            if (!$product) return '#';

            $api_key = $this->get_secret_key();
            $base_url = 'https://' . $_SERVER['HTTP_HOST'];
            
            // Use your existing helper for price data
            $price_data = $this->get_stripe_price_for_product($product);
            $line_item = array('quantity' => 1);

            if (is_string($price_data)) {
                $line_item['price'] = $price_data;
            } else {
                $line_item['price_data'] = $price_data;
            }

            $session_data = array(
                'mode' => !empty($product['is_recurring']) ? 'subscription' : 'payment',
                'line_items' => array($line_item),
                // Redirect back to your existing success handler
                'success_url' => $base_url . '/wpwa-paddle-checkout?action=success&_ptxn=' . $transaction['paddle_transaction_id'],
                'cancel_url'  => $base_url . '/wpwa-paddle-checkout?action=cancel',
                'metadata' => array(
                    'internal_txn_id' => $transaction['paddle_transaction_id'],
                    'weebly_user_id'  => $transaction['weebly_user_id'],
                    'product_id'      => $transaction['product_id'],
                    'gateway'         => 'stripe'
                )
            );

            $response = $this->stripe_api_request('checkout/sessions', 'POST', $session_data, $api_key);

            if ($response && isset($response['url'])) {
                // Update the existing record to note that Stripe was initiated
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'wpwa_paddle_transactions',
                    array('paddle_customer_id' => $response['customer'] ?? ''),
                    array('paddle_transaction_id' => $transaction['paddle_transaction_id'])
                );
                
                return $response['url'];
            }

            return '#';
        } catch (Exception $e) {
            return '#';
        }
    }
    
    public function register_settings() {
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_enabled');
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_test_mode');
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_test_publishable_key');
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_test_secret_key');
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_live_publishable_key');
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_live_secret_key');
        register_setting('wpwa_stripe_settings', 'wpwa_stripe_webhook_secret');
    }
    
    public function add_settings_submenu() {
        add_submenu_page(
            'wpwa-paddle',
            'Stripe Gateway',
            'Stripe Gateway',
            'manage_options',
            'wpwa-stripe-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Stripe Gateway Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('wpwa_stripe_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Enable Stripe</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpwa_stripe_enabled" value="yes" <?php checked(get_option('wpwa_stripe_enabled'), 'yes'); ?>>
                                Enable Stripe Payment Gateway
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Test Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpwa_stripe_test_mode" value="yes" <?php checked(get_option('wpwa_stripe_test_mode'), 'yes'); ?>>
                                Use Test Mode (Sandbox)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2"><h3>Test Keys</h3></th>
                    </tr>
                    <tr>
                        <th>Test Publishable Key</th>
                        <td><input type="text" name="wpwa_stripe_test_publishable_key" value="<?php echo esc_attr(get_option('wpwa_stripe_test_publishable_key')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Test Secret Key</th>
                        <td><input type="password" name="wpwa_stripe_test_secret_key" value="<?php echo esc_attr(get_option('wpwa_stripe_test_secret_key')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th colspan="2"><h3>Live Keys</h3></th>
                    </tr>
                    <tr>
                        <th>Live Publishable Key</th>
                        <td><input type="text" name="wpwa_stripe_live_publishable_key" value="<?php echo esc_attr(get_option('wpwa_stripe_live_publishable_key')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Live Secret Key</th>
                        <td><input type="password" name="wpwa_stripe_live_secret_key" value="<?php echo esc_attr(get_option('wpwa_stripe_live_secret_key')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Webhook Signing Secret</th>
                        <td>
                            <input type="password" name="wpwa_stripe_webhook_secret" value="<?php echo esc_attr(get_option('wpwa_stripe_webhook_secret')); ?>" class="regular-text">
                            <p class="description">Webhook URL: <?php echo home_url('/wpwa-stripe-webhook'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function enqueue_stripe_js($hook) {
        if (strpos($hook, 'wpwa-stripe') === false) return;
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
    }
    
    public function check_stripe_access($result, $weebly_user_id, $product_id, $site_id) {
        if ($result !== null) return $result; // Already handled
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_transactions';
        
        // Check for completed Stripe transaction (identified by stripe_ prefix)
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` 
             WHERE weebly_user_id = %s
             AND weebly_site_id = %s 
             AND product_id = %d 
             AND paddle_transaction_id LIKE 'stripe_%'
             AND status = 'completed' 
             ORDER BY created_at DESC 
             LIMIT 1",
            $weebly_user_id,
            $site_id,
            $product_id
        ), ARRAY_A);
        
        if ($transaction) {
            return array(
                'has_access' => true,
                'source' => 'stripe_purchase',
                'details' => array(
                    'transaction_id' => $transaction['id'],
                    'purchase_date' => $transaction['created_at'],
                    'amount' => $transaction['amount']
                )
            );
        }
        
        // Check for active Stripe subscription
        $sub_table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$sub_table}` 
             WHERE weebly_user_id = %s
             AND weebly_site_id = %s 
             AND product_id = %d 
             AND paddle_subscription_id LIKE 'stripe_%'
             AND status IN ('active', 'trialing') 
             AND current_period_end > NOW()",
            $weebly_user_id,
            $site_id,
            $product_id
        ), ARRAY_A);
        
        if ($subscription) {
            return array(
                'has_access' => true,
                'source' => 'stripe_subscription',
                'details' => array(
                    'subscription_id' => $subscription['paddle_subscription_id'],
                    'current_period_end' => $subscription['current_period_end']
                )
            );
        }
        
        return null; // Let other checks continue
    }
    
    public function update_stripe_token($handled, $weebly_user_id, $weebly_site_id, $product_id, $encrypted_token, $source) {
        if (!in_array($source, array('stripe_purchase', 'stripe_subscription'))) {
            return false;
        }
        
        global $wpdb;
        
        if ($source === 'stripe_subscription') {
            $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
            $wpdb->update(
                $table,
                array('access_token' => $encrypted_token),
                array('weebly_user_id' => $weebly_user_id, 'weebly_site_id' => $weebly_site_id, 'product_id' => $product_id)
            );
        } else {
            $table = $wpdb->prefix . 'wpwa_paddle_transactions';
            $wpdb->update(
                $table,
                array('access_token' => $encrypted_token),
                array('weebly_user_id' => $weebly_user_id, 'weebly_site_id' => $weebly_site_id, 'product_id' => $product_id)
            );
        }
        
        return true;
    }
    
    public function add_access_labels($labels) {
        $labels['stripe_purchase'] = '💳 Purchased (Stripe)';
        $labels['stripe_subscription'] = '🔄 Active Subscription (Stripe)';
        return $labels;
    }
    
    public function register_routes($routes) {
        $routes['wpwa-stripe-checkout'] = array($this, 'handle_checkout_redirect');
        $routes['wpwa-stripe-webhook'] = array($this, 'handle_webhook');
        return $routes;
    }
    
    public function handle_checkout_redirect() {
        $action = $_GET['action'] ?? '';
        $session_id = $_GET['session_id'] ?? '';
        
        if (empty($session_id)) {
            wp_die('Invalid session');
        }
        
        if ($action === 'success') {
            // Fetch session from Stripe to verify
            $api_key = $this->get_secret_key();
            $session = $this->stripe_api_request("checkout/sessions/{$session_id}", 'GET', null, $api_key);
            
            if ($session && $session['payment_status'] === 'paid') {
                $final_url = $session['metadata']['final_url'] ?? '';
                if ($final_url) {
                    add_filter('allowed_redirect_hosts', function($hosts) {
                        $hosts[] = 'www.weebly.com';
                        return $hosts;
                    });
                    wp_safe_redirect($final_url);
                    exit;
                }
            }
        }
        
        wp_die('Payment cancelled or failed');
    }
    
    public function handle_webhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        $webhook_secret = get_option('wpwa_stripe_webhook_secret');
        
        if (empty($webhook_secret)) {
            http_response_code(500);
            exit;
        }
        
        // Verify signature
        if (!$this->verify_webhook_signature($payload, $sig_header, $webhook_secret)) {
            http_response_code(401);
            exit;
        }
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            http_response_code(400);
            exit;
        }
        
        // Log webhook
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_webhook_log';
        $wpdb->insert($table, array(
            'event_id' => $event['id'] ?? uniqid('stripe_'),
            'event_type' => 'stripe.' . ($event['type'] ?? 'unknown'),
            'payload' => $payload,
            'processed' => 0
        ));
        
        // Process event
        $this->process_stripe_webhook($event);
        
        http_response_code(200);
        echo json_encode(array('received' => true));
        exit;
    }
    
    private function process_stripe_webhook($event) {
        global $wpdb;
        
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? array();
        
        switch ($type) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed($data);
                break;
                
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded($data);
                break;
                
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handle_subscription_updated($data);
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_cancelled($data);
                break;
        }
    }
    
    private function handle_checkout_completed($session) {
        global $wpdb;
        
        $metadata = $session['metadata'] ?? array();
        $session_id = $session['id'];
        
        $table = $wpdb->prefix . 'wpwa_paddle_transactions';
        
        // Update transaction status
        $wpdb->update(
            $table,
            array('status' => 'completed'),
            array('paddle_transaction_id' => 'stripe_' . $session_id)
        );
        
        // If subscription, create subscription record
        if ($session['mode'] === 'subscription' && !empty($session['subscription'])) {
            $this->create_subscription_record($session);
        }
        
    }
    
    private function handle_payment_succeeded($payment_intent) {
        // Additional confirmation - update transaction
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_transactions';
        
        $wpdb->update(
            $table,
            array('status' => 'completed'),
            array('paddle_customer_id' => $payment_intent['customer'])
        );
    }
    
    private function create_subscription_record($session) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
        
        $metadata = $session['metadata'];
        $subscription_id = $session['subscription'];
        
        // Fetch subscription details from Stripe
        $api_key = $this->get_secret_key();
        $sub = $this->stripe_api_request("subscriptions/{$subscription_id}", 'GET', null, $api_key);
        
        if (!$sub) return;
        
        $wpdb->insert($table, array(
            'paddle_subscription_id' => 'stripe_' . $subscription_id,
            'paddle_customer_id' => $sub['customer'] ?? '',
            'weebly_user_id' => $metadata['weebly_user_id'],
            'weebly_site_id' => $metadata['weebly_site_id'] ?? '',
            'product_id' => $metadata['product_id'],
            'paddle_price_id' => $sub['items']['data'][0]['price']['id'] ?? '',
            'status' => $sub['status'],
            'current_period_start' => date('Y-m-d H:i:s', $sub['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $sub['current_period_end']),
            'access_token' => $metadata['access_token'],
            'metadata' => json_encode(array('gateway' => 'stripe')),
            'created_at' => current_time('mysql')
        ));
    }
    
    private function handle_subscription_updated($subscription) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
        
        $wpdb->update(
            $table,
            array(
                'status' => $subscription['status'],
                'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
                'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end'])
            ),
            array('paddle_subscription_id' => 'stripe_' . $subscription['id'])
        );
    }
    
    private function handle_subscription_cancelled($subscription) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_subscriptions';
        
        $wpdb->update(
            $table,
            array('status' => 'canceled'),
            array('paddle_subscription_id' => 'stripe_' . $subscription['id'])
        );
    }
    
    private function get_stripe_price_for_product($product) {
        $post_id = $product['id'];
        // 1. Check for a manually entered Stripe Price ID from Product Meta
        // This is the most reliable way if you have created the price in Stripe Dashboard
        $stripe_price_id = get_post_meta($post_id, '_wpwa_stripe_price_id', true);
        
        if (!empty($stripe_price_id)) {
            return $stripe_price_id; 
        }

        // 2. DYNAMIC FALLBACK: Generate price data from product details
        // Fetch values using the keys defined in your wpwa_paddle_save_product_meta function
        $raw_price    = get_post_meta($post_id, '_wpwa_paddle_price', true);
        $is_recurring = get_post_meta($post_id, '_wpwa_paddle_is_recurring', true) === '1';
        $currency     = get_option('wpwa_paddle_currency', 'USD'); // Pull global currency setting

        $price_data = array(
            'currency'    => strtolower($currency),
            'unit_amount' => (int)(floatval($raw_price) * 100), // Stripe requires amount in cents
            'product_data' => array(
                'name' => get_the_title($post_id),
            ),
        );

        // 3. Handle Recurring Settings for Subscriptions
        if ($is_recurring) {
            $interval  = get_post_meta($post_id, '_wpwa_paddle_billing_cycle', true);    // e.g., 'month', 'year'
            $frequency = get_post_meta($post_id, '_wpwa_paddle_billing_frequency', true); // e.g., 1, 12

            $price_data['recurring'] = array(
                'interval'       => !empty($interval) ? strtolower($interval) : 'year',
                'interval_count' => !empty($frequency) ? absint($frequency) : 1,
            );
        }

        return $price_data;
    }
    
    private function stripe_api_request($endpoint, $method = 'GET', $data = null, $api_key = null) {
        if (!$api_key) {
            $api_key = $this->get_secret_key();
        }
        
        $url = 'https://api.stripe.com/v1/' . $endpoint;
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = $this->build_query($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Stripe API error: ' . $response->get_error_message());
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    private function build_query($data, $prefix = '') {
        $query = array();
        
        foreach ($data as $key => $value) {
            $k = $prefix ? "{$prefix}[{$key}]" : $key;
            
            if (is_array($value)) {
                $query[] = $this->build_query($value, $k);
            } else {
                $query[] = urlencode($k) . '=' . urlencode($value);
            }
        }
        
        return implode('&', $query);
    }
    
    private function verify_webhook_signature($payload, $sig_header, $secret) {
        if (empty($sig_header)) return false;
        
        $parts = explode(',', $sig_header);
        $timestamp = null;
        $signature = null;
        
        foreach ($parts as $part) {
            list($key, $value) = explode('=', $part, 2);
            if ($key === 't') $timestamp = $value;
            if ($key === 'v1') $signature = $value;
        }
        
        if (!$timestamp || !$signature) return false;
        
        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $secret);
        
        return hash_equals($expected_signature, $signature);
    }
    
    private function is_enabled() {
        return get_option('wpwa_stripe_enabled') === 'yes';
    }
    
    private function is_test_mode() {
        return get_option('wpwa_stripe_test_mode') === 'yes';
    }
    
    private function get_secret_key() {
        if ($this->is_test_mode()) {
            return get_option('wpwa_stripe_test_secret_key');
        }
        return get_option('wpwa_stripe_live_secret_key');
    }
    
    private function get_publishable_key() {
        if ($this->is_test_mode()) {
            return get_option('wpwa_stripe_test_publishable_key');
        }
        return get_option('wpwa_stripe_live_publishable_key');
    }
}

// Initialize
WPWA_Stripe_Extension::instance();