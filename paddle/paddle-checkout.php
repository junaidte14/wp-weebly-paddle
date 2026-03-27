<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_create_checkout_transaction($args) {
    $required = array('product_id', 'weebly_user_id', 'access_token', 'final_url');
    foreach ($required as $key) {
        if (empty($args[$key])) {
            return array('success' => false, 'message' => "Missing: {$key}");
        }
    }
    
    $product = wpwa_paddle_get_or_sync_product($args['product_id']);
    if (!$product) {
        return array('success' => false, 'message' => 'Product not found');
    }
    
    $email = !empty($args['email']) ? $args['email'] : '';
    $is_recurring = !empty($product['is_recurring']);
    
    try {
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa-paddle-checkout/';
        
        $custom_data = array(
            'weebly_user_id' => $args['weebly_user_id'],
            'weebly_site_id' => $args['weebly_site_id'] ?? '',
            'product_id' => $args['product_id'],
            'access_token' => wpwa_paddle_encrypt_token($args['access_token']),
            'final_url' => $args['final_url'],
            'app_source' => 'weebly_licenses'
        );
        
        $items = array(
            array(
                'price_id' => $product['paddle_price_id'],
                'quantity' => 1
            )
        );
        
        $transaction_data = array(
            'items' => $items,
            'custom_data' => $custom_data
        );
        
        if (!empty($email)) {
            $customer_id = wpwa_paddle_get_or_create_customer($args['weebly_user_id'], $email, $args['name'] ?? '');
            if ($customer_id) {
                $transaction_data['customer_id'] = $customer_id;
            }
        }
        
        $result = wpwa_paddle_api_request('/transactions', 'POST', $transaction_data);
        
        if (!$result['success']) {
            return $result;
        }
        
        $transaction = $result['data'];

        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_paddle_transactions';

        $wpdb->insert($table, [
            'paddle_transaction_id' => $transaction['id'],
            'weebly_user_id' => $args['weebly_user_id'],
            'weebly_site_id' => $args['weebly_site_id'],
            'product_id' => $args['product_id'],
            'final_url' => $args['final_url'],
            'access_token' => $args['access_token'], // encrypted already
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);
        
        return array(
            'success' => true,
            'transaction_id' => $transaction['id'],
            'checkout_url' => $transaction['checkout']['url'] ?? null
        );
        
    } catch (Exception $e) {
        wpwa_paddle_log('Checkout error: ' . $e->getMessage());
        return array('success' => false, 'message' => $e->getMessage());
    }
}

function wpwa_paddle_checkout_router() {
    wpwa_paddle_log("Checkout router hit", $_GET);

    $txn_id = isset($_GET['_ptxn']) ? sanitize_text_field($_GET['_ptxn']) : '';

    if (!$txn_id) {
        wpwa_paddle_log("Missing txn id");
        wp_die('Invalid transaction');
    }

    // Handle success/cancel first
    $action = $_GET['action'] ?? '';

    if ($action === 'success') {
        wpwa_paddle_handle_checkout_success();
        return;
    }

    if ($action === 'cancel') {
        wpwa_paddle_handle_checkout_cancel();
        return;
    }

    // 🔥 DEFAULT: Render checkout page
    wpwa_paddle_render_checkout_page($txn_id);
}

function wpwa_paddle_render_checkout_page($txn_id) {
    wpwa_paddle_log("Rendering checkout page", $txn_id);

    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';

    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE paddle_transaction_id = %s",
        $txn_id
    ), ARRAY_A);

    if (!$transaction) {
        wpwa_paddle_log("Transaction not found", $txn_id);
        wp_die('Transaction not found');
    }

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Complete Your Payment</title>
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    </head>
    <body>
        <h2>Complete Your Payment</h2>
        <p>Transaction ID: <?php echo esc_html($txn_id); ?></p>

        <button id="payBtn">Pay Now</button>

        <script>
            Paddle.Initialize({
                token: "<?php echo esc_js(get_option('wpwa_paddle_client_token')); ?>"
            });

            document.getElementById('payBtn').addEventListener('click', function () {
                Paddle.Checkout.open({
                    transactionId: "<?php echo esc_js($txn_id); ?>",
                    settings: {
                        successUrl: "<?php echo esc_url(home_url('/wpwa-paddle-checkout?action=success&_ptxn=' . $txn_id)); ?>",
                        closeUrl: "<?php echo esc_url(home_url('/wpwa-paddle-checkout?action=cancel')); ?>"
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

function wpwa_paddle_handle_checkout_success() {
    if (!isset($_GET['_ptxn'])) {
        wp_die('Invalid transaction');
    }
    
    $paddle_txn_id = sanitize_text_field($_GET['_ptxn']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';
    
    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE paddle_transaction_id = %s",
        $paddle_txn_id
    ), ARRAY_A);
    
    if ($transaction && !empty($transaction['final_url'])) {
        wp_redirect($transaction['final_url']);
        exit;
    }
    
    wp_die('Transaction not found');
}

function wpwa_paddle_handle_checkout_cancel() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Cancelled</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: 0 auto; }
            h1 { color: #e74c3c; }
            .button { display: inline-block; padding: 12px 24px; background: #3498db; 
                     color: #fff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Payment Cancelled</h1>
            <p>Your payment was cancelled. You can try again or contact support.</p>
            <a href="<?php echo home_url(); ?>" class="button">Return Home</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}