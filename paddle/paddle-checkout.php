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
            'access_token' => $custom_data['access_token'],
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

    // 🔥 Load settings
    $sandbox_mode = wpwa_paddle_get_option('sandbox_mode') === 'yes';

    $client_token = $sandbox_mode
        ? wpwa_paddle_get_option('sandbox_client_token')
        : wpwa_paddle_get_option('live_client_token');

    $environment = $sandbox_mode ? 'sandbox' : 'production';

    $price_id = wpwa_paddle_get_product($transaction['product_id'])['paddle_price_id'] ?? '';

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Complete Your Payment</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>

        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial;
                background: #f5f7fb;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
            }

            .card {
                background: #fff;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.08);
                max-width: 420px;
                width: 100%;
                text-align: center;
            }

            h2 {
                margin-bottom: 10px;
                color: #222;
            }

            p {
                color: #666;
                font-size: 14px;
                margin-bottom: 25px;
            }

            .btn {
                background: #635bff;
                color: #fff;
                border: none;
                padding: 14px 20px;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                width: 100%;
                transition: 0.2s;
            }

            .btn:hover {
                background: #5148e5;
            }

            .btn.loading {
                opacity: 0.7;
                pointer-events: none;
            }

            .loader {
                margin-top: 15px;
                font-size: 13px;
                color: #888;
                display: none;
            }
        </style>
    </head>
    <body>

        <div class="card">
            <h2>Complete Your Payment</h2>
            <p>Secure checkout powered by Paddle</p>

            <button id="payBtn" class="btn">Pay Now</button>

            <div class="loader" id="loader">Opening secure checkout...</div>
        </div>

        <script>
            // 🔥 Set environment
            Paddle.Environment.set("<?php echo esc_js($environment); ?>");

            // 🔥 Init Paddle
            Paddle.Initialize({
                token: "<?php echo esc_js($client_token); ?>",
                eventCallback: function(event) {
                    console.log("Paddle event:", event);
                    // 🔥 Payment completed
                    if (event.name === "checkout.completed") {
                        window.location.href = "<?php echo esc_url_raw(home_url('/wpwa-paddle-checkout?action=success&_ptxn=' . $txn_id)); ?>";
                    }
                }
            });

            const btn = document.getElementById('payBtn');
            const loader = document.getElementById('loader');

            btn.addEventListener('click', function () {
                btn.classList.add('loading');
                loader.style.display = 'block';

                Paddle.Checkout.open({
                    items: [{
                        priceId: "<?php echo esc_js($price_id); ?>",
                        quantity: 1
                    }],

                    // 🔥 Pass custom data again (important for webhook consistency)
                    customData: {
                        weebly_user_id: "<?php echo esc_js($transaction['weebly_user_id']); ?>",
                        weebly_site_id: "<?php echo esc_js($transaction['weebly_site_id']); ?>",
                        product_id: "<?php echo esc_js($transaction['product_id']); ?>",
                        txn_ref: "<?php echo esc_js($txn_id); ?>"
                    },

                    settings: {
                        displayMode: "overlay",
                        theme: "light",
                        locale: "en",

                        successUrl: "<?php echo esc_url_raw(home_url('/wpwa-paddle-checkout?action=success&_ptxn=' . $txn_id)); ?>",
                        closeUrl: "<?php echo esc_url_raw(home_url('/wpwa-paddle-checkout?action=cancel')); ?>"
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