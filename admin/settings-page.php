<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-paddle'));
    }
    
    if (isset($_POST['wpwa_paddle_settings_nonce']) && 
        wp_verify_nonce($_POST['wpwa_paddle_settings_nonce'], 'wpwa_paddle_settings')) {
        wpwa_paddle_save_settings();
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    $enabled = wpwa_paddle_get_option('enabled', 'no');
    $sandbox_mode = wpwa_paddle_get_option('sandbox_mode', 'yes');
    $sandbox_api_key = wpwa_paddle_get_option('sandbox_api_key');
    $sandbox_client_token = wpwa_paddle_get_option('sandbox_client_token');
    $sandbox_webhook_secret = wpwa_paddle_get_option('sandbox_webhook_secret');
    $live_api_key = wpwa_paddle_get_option('live_api_key');
    $live_client_token = wpwa_paddle_get_option('live_client_token');
    $live_webhook_secret = wpwa_paddle_get_option('live_webhook_secret');
    
    $webhook_url = home_url('/wpwa-paddle-webhook/');

    //Stripe Integration
    $stripe_enabled = wpwa_stripe_get_option('enabled', 'no');
    $stripe_test_mode = wpwa_stripe_get_option('test_mode', 'yes');
    $stripe_test_pub_key = wpwa_stripe_get_option('test_publishable_key');
    $stripe_test_secret_key = wpwa_stripe_get_option('test_secret_key');
    $stripe_test_webhook_secret = wpwa_stripe_get_option('test_webhook_secret');
    $stripe_live_pub_key = wpwa_stripe_get_option('live_publishable_key');
    $stripe_live_secret_key = wpwa_stripe_get_option('live_secret_key');
    $stripe_live_webhook_secret = wpwa_stripe_get_option('live_webhook_secret');
    
    $stripe_webhook_url = home_url('/wpwa-stripe-webhook/');
    ?>
    <div class="wrap wpwa-paddle-wrap">
        <h1>
            <span class="dashicons dashicons-admin-settings"></span>
            Paddle Settings
        </h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('wpwa_paddle_settings', 'wpwa_paddle_settings_nonce'); ?>
            
            <div class="wpwa-settings-section">
                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Paddle</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                                Enable Paddle payment processing
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sandbox Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sandbox_mode" value="yes" <?php checked($sandbox_mode, 'yes'); ?>>
                                Enable sandbox mode (use test credentials)
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wpwa-settings-section">
                <h2>Sandbox Credentials</h2>
                <p class="description">Get your sandbox credentials from <a href="https://sandbox-vendors.paddle.com/authentication" target="_blank">Paddle Sandbox Dashboard</a></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" name="sandbox_api_key" 
                                   value="<?php echo esc_attr($sandbox_api_key); ?>" 
                                   class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Token</th>
                        <td>
                            <input type="text" name="sandbox_client_token" 
                                   value="<?php echo esc_attr($sandbox_client_token); ?>" 
                                   class="regular-text code">
                            <p class="description">Used for Paddle.js checkout overlay</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td>
                            <input type="password" name="sandbox_webhook_secret" 
                                   value="<?php echo esc_attr($sandbox_webhook_secret); ?>" 
                                   class="regular-text code">
                            <p class="description">
                                Webhook URL: <code><?php echo esc_html($webhook_url); ?></code>
                                <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">Copy</button>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpwa-settings-section">
                <h2>Whitelist Product Configuration</h2>
                <p class="description">Configure the Paddle product/price used for whitelist purchases</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Whitelist Product ID</th>
                        <td>
                            <input type="text" name="whitelist_product_id" 
                                value="<?php echo esc_attr(wpwa_paddle_get_option('whitelist_product_id')); ?>" 
                                class="regular-text">
                            <p class="description">Paddle Product ID for whitelist purchases</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Whitelist Price ID</th>
                        <td>
                            <input type="text" name="whitelist_price_id" 
                                value="<?php echo esc_attr(wpwa_paddle_get_option('whitelist_price_id')); ?>" 
                                class="regular-text">
                            <p class="description">Paddle Price ID for whitelist purchases</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wpwa-settings-section">
                <h2>Live Credentials</h2>
                <p class="description">Get your live credentials from <a href="https://vendors.paddle.com/authentication" target="_blank">Paddle Live Dashboard</a></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" name="live_api_key" 
                                   value="<?php echo esc_attr($live_api_key); ?>" 
                                   class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Token</th>
                        <td>
                            <input type="text" name="live_client_token" 
                                   value="<?php echo esc_attr($live_client_token); ?>" 
                                   class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td>
                            <input type="password" name="live_webhook_secret" 
                                   value="<?php echo esc_attr($live_webhook_secret); ?>" 
                                   class="regular-text code">
                        </td>
                    </tr>
                </table>
            </div>

            <h1>Stripe Settings</h1>
            <hr>
            <!-- Enable/Disable -->
            <div class="wpwa-settings-section">
                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Stripe</th>
                        <td>
                            <label>
                                <input type="checkbox" name="stripe_enabled" value="yes" <?php checked($stripe_enabled, 'yes'); ?>>
                                Enable Stripe payment processing
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="stripe_test_mode" value="yes" <?php checked($stripe_test_mode, 'yes'); ?>>
                                Enable test mode (use test API keys)
                            </label>
                            <p class="description">Use test mode for development and testing. Disable for live payments.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Test Keys -->
            <div class="wpwa-settings-section">
                <h2>Test API Keys</h2>
                <p class="description">Get your test keys from <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard (Test Mode)</a></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="stripe_test_publishable_key">Publishable Key</label>
                        </th>
                        <td>
                            <input type="text" id="stripe_test_publishable_key" name="stripe_test_publishable_key" 
                                   value="<?php echo esc_attr($stripe_test_pub_key); ?>" 
                                   class="regular-text code" placeholder="pk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="stripe_test_secret_key">Secret Key</label>
                        </th>
                        <td>
                            <input type="password" id="stripe_test_secret_key" name="stripe_test_secret_key" 
                                   value="<?php echo esc_attr($stripe_test_secret_key); ?>" 
                                   class="regular-text code" placeholder="sk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="stripe_test_webhook_secret">Webhook Secret</label>
                        </th>
                        <td>
                            <input type="password" id="stripe_test_webhook_secret" name="stripe_test_webhook_secret" 
                                   value="<?php echo esc_attr($stripe_test_webhook_secret); ?>" 
                                   class="regular-text code" placeholder="whsec_...">
                            <p class="description">
                                Webhook URL: <code><?php echo esc_html($stripe_webhook_url); ?></code>
                                <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($stripe_webhook_url); ?>')">Copy</button>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Live Keys -->
            <div class="wpwa-settings-section">
                <h2>Live API Keys</h2>
                <p class="description">Get your live keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard (Live Mode)</a></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="stripe_live_publishable_key">Publishable Key</label>
                        </th>
                        <td>
                            <input type="text" id="stripe_live_publishable_key" name="stripe_live_publishable_key" 
                                   value="<?php echo esc_attr($stripe_live_pub_key); ?>" 
                                   class="regular-text code" placeholder="pk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="stripe_live_secret_key">Secret Key</label>
                        </th>
                        <td>
                            <input type="password" id="stripe_live_secret_key" name="stripe_live_secret_key" 
                                   value="<?php echo esc_attr($stripe_live_secret_key); ?>" 
                                   class="regular-text code" placeholder="sk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="stripe_live_webhook_secret">Webhook Secret</label>
                        </th>
                        <td>
                            <input type="password" id="stripe_live_webhook_secret" name="stripe_live_webhook_secret" 
                                   value="<?php echo esc_attr($stripe_live_webhook_secret); ?>" 
                                   class="regular-text code" placeholder="whsec_...">
                            <p class="description">
                                Webhook URL: <code><?php echo esc_html($stripe_webhook_url); ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    
    <style>
    .wpwa-settings-section {
        margin-bottom: 30px;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
    }
    .wpwa-settings-section h2 {
        margin-top: 0;
    }
    .form-table th {
        width: 250px;
    }
    </style>
    <?php
}

function wpwa_paddle_save_settings() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wpwa_paddle_update_option('enabled', isset($_POST['enabled']) ? 'yes' : 'no');
    wpwa_paddle_update_option('sandbox_mode', isset($_POST['sandbox_mode']) ? 'yes' : 'no');
    
    wpwa_paddle_update_option('sandbox_api_key', sanitize_text_field($_POST['sandbox_api_key']));
    wpwa_paddle_update_option('sandbox_client_token', sanitize_text_field($_POST['sandbox_client_token']));
    wpwa_paddle_update_option('sandbox_webhook_secret', sanitize_text_field($_POST['sandbox_webhook_secret']));
    
    wpwa_paddle_update_option('live_api_key', sanitize_text_field($_POST['live_api_key']));
    wpwa_paddle_update_option('live_client_token', sanitize_text_field($_POST['live_client_token']));
    wpwa_paddle_update_option('live_webhook_secret', sanitize_text_field($_POST['live_webhook_secret']));

    wpwa_paddle_update_option('whitelist_product_id', sanitize_text_field($_POST['whitelist_product_id']));
    wpwa_paddle_update_option('whitelist_price_id', sanitize_text_field($_POST['whitelist_price_id']));

    wpwa_stripe_update_option('enabled', isset($_POST['stripe_enabled']) ? 'yes' : 'no');
    wpwa_stripe_update_option('test_mode', isset($_POST['stripe_test_mode']) ? 'yes' : 'no');
    
    wpwa_stripe_update_option('test_publishable_key', sanitize_text_field($_POST['stripe_test_publishable_key']));
    wpwa_stripe_update_option('test_secret_key', sanitize_text_field($_POST['stripe_test_secret_key']));
    wpwa_stripe_update_option('test_webhook_secret', sanitize_text_field($_POST['stripe_test_webhook_secret']));
    
    wpwa_stripe_update_option('live_publishable_key', sanitize_text_field($_POST['stripe_live_publishable_key']));
    wpwa_stripe_update_option('live_secret_key', sanitize_text_field($_POST['stripe_live_secret_key']));
    wpwa_stripe_update_option('live_webhook_secret', sanitize_text_field($_POST['stripe_live_webhook_secret']));


}