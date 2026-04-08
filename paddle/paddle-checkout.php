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
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_paddle_transactions';

    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE paddle_transaction_id = %s",
        $txn_id
    ), ARRAY_A);

    if (!$transaction) {
        wp_die('Transaction not found');
    }

    // 1. Fetch Product Data
    $product = wpwa_paddle_get_product($transaction['product_id']);
    $price_val = $product['price'] ?? 0.00;
    $display_price = number_format($price_val, 2);
    
    $billing_text = 'one-time payment';
    if (!empty($product['is_recurring'])) {
        $unit = $product['cycle_unit'] ?? 'year';
        $billing_text = 'per ' . $unit;
    }

    // 2. Settings & Identity
    $sandbox_mode = wpwa_paddle_get_option('sandbox_mode') === 'yes';
    $client_token = $sandbox_mode ? wpwa_paddle_get_option('sandbox_client_token') : wpwa_paddle_get_option('live_client_token');
    $environment  = $sandbox_mode ? 'sandbox' : 'production';
    
    $site_title = get_bloginfo('name');
    $logo_url   = 'https://codoplex.com/wp-content/uploads/2022/05/cropped-Logo-Icon-300x300codoplex-300x300-1-100x100.png';
    $upsell_url = 'https://codoplex.com/get-weebly-apps-subscription/';

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="UTF-8">
        <title>Complete Your Purchase - <?php echo esc_html($site_title); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f8fafc; margin: 0; padding: 0; }
            .wpwa-checkout-page { padding: 2px 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; box-sizing: border-box; }
            .checkout-container { background: white; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); max-width: 480px; width: 100%; border: 1px solid #e2e8f0; overflow: hidden; }
            
            /* Header */
            .card-header { padding: 5px; border-bottom: 1px solid #f1f5f9; text-align: center; }
            .card-header img { max-height: 50px; margin-bottom: 10px; }
            .card-header h2 { margin: 0; font-size: 16px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1.2px; }

            /* Body */
            .card-body { padding: 5px 30px; text-align: center; }
            .product-badge { background: #e0e7ff; color: #4338ca; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
            .card-body h1 { margin: 10px 0; font-size: 26px; color: #1e293b; font-weight: 800; }
            
            /* Pricing */
            .price-container { margin: 5px 0 20px 0; }
            .price-amount { font-size: 48px; font-weight: 800; color: #10b981; display: block; line-height: 1; }
            .price-currency { font-size: 20px; vertical-align: top; margin-right: 2px; position: relative; top: 8px; }
            .price-period { font-size: 14px; color: #94a3b8; font-weight: 500; margin-top: 8px; display: block; }

            /* Info Box */
            .details-box { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 10px; padding: 18px; margin-bottom: 25px; text-align: left; }
            .details-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
            .details-row:last-child { margin-bottom: 0; }
            .details-row label { color: #94a3b8; font-weight: 500; }
            .details-row span { color: #1e293b; font-family: 'Courier New', monospace; font-weight: 600; }

            /* Buttons */
            .btn-paddle { display: block; width: 100%; background: #6366f1; color: white; border: none; padding: 18px; border-radius: 10px; font-weight: 700; font-size: 18px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
            .btn-paddle:hover { background: #4f46e5; transform: translateY(-1px); }
            .btn-paddle:active { transform: translateY(0); }
            .btn-paddle.loading { opacity: 0.7; pointer-events: none; }

            .btn-upsell { display: block; color: #6366f1; text-decoration: none; padding: 12px; font-weight: 600; margin-top: 15px; font-size: 14px; border: 1px dashed #cbd5e1; border-radius: 8px; }
            .btn-upsell:hover { background: #f5f7ff; border-color: #6366f1; }

            /* Footer */
            .card-footer { background: #f8fafc; padding: 20px; border-top: 1px solid #f1f5f9; text-align: center; }
            .footer-links a { font-size: 12px; color: #94a3b8; text-decoration: none; margin: 0 10px; }
            .footer-links a:hover { color: #6366f1; }
            .copyright { font-size: 11px; color: #cbd5e1; margin-top: 15px; }
        </style>
    </head>
    <body>

    <div class="wpwa-checkout-page">
        <div class="checkout-container">
            <div class="card-header">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                <?php endif; ?>
                <h2><?php echo esc_html($site_title); ?></h2>
            </div>

            <div class="card-body">
                <div class="product-badge">Confirm Secure Checkout</div>
                <h1><?php echo esc_html($product['name'] ?? 'Product Selection'); ?></h1>
                
                <div class="price-container">
                    <span class="price-amount">
                        <span class="price-currency">$</span><?php echo esc_html($display_price); ?>
                    </span>
                    <span class="price-period"><?php echo esc_html($billing_text); ?></span>
                </div>

                <div class="details-box">
                    <div class="details-row">
                        <label>User ID</label>
                        <span><?php echo esc_html($transaction['weebly_user_id']); ?></span>
                    </div>
                    <div class="details-row">
                        <label>Site ID</label>
                        <span><?php echo esc_html($transaction['weebly_site_id']); ?></span>
                    </div>
                </div>

                <button id="payBtn" class="btn-paddle">Proceed to Secure Payment →</button>

                <a href="<?php echo esc_url($upsell_url . '?user_id=' . $transaction['weebly_user_id'] . '&site_id=' . $transaction['weebly_site_id']); ?>" target="_blank" class="btn-upsell">
                    🚀 Upgrade to All-in-One Whitelist
                </a>
            </div>

            <div class="card-footer">
                <div class="footer-links">
                    <a href="https://codoplex.com/privacy-policy/" target="_blank">Privacy</a>
                    <a href="https://codoplex.com/terms-and-conditions/" target="_blank">Terms</a>
                    <a href="https://codoplex.com/refund-policy/" target="_blank">Refunds</a>
                    <a href="https://codoplex.com/contact/" target="_blank">Contact</a>
                </div>
                <p class="copyright">© <?php echo date('Y'); ?> Codoplex. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        Paddle.Environment.set("<?php echo esc_js($environment); ?>");
        Paddle.Initialize({
            token: "<?php echo esc_js($client_token); ?>",
            eventCallback: function(event) {
                if (event.name === "checkout.completed") {
                    window.location.href = "<?php echo esc_url_raw(home_url('/wpwa-paddle-checkout?action=success&_ptxn=' . $txn_id)); ?>";
                }
            }
        });

        const btn = document.getElementById('payBtn');
        btn.addEventListener('click', function () {
            btn.classList.add('loading');
            btn.innerText = "Opening Checkout...";

            Paddle.Checkout.open({
                items: [{
                    priceId: "<?php echo esc_js($product['paddle_price_id']); ?>",
                    quantity: 1
                }],
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
                    successUrl: "<?php echo esc_url_raw(home_url('/wpwa-paddle-checkout?action=success&_ptxn=' . $txn_id)); ?>"
                }
            });
            
            // Re-enable button if user closes overlay without finishing
            setTimeout(() => {
                btn.classList.remove('loading');
                btn.innerText = "Proceed to Secure Payment →";
            }, 5000);
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