<?php
/**
 * Plugin Name: WP Weebly Apps - Paddle Edition
 * Plugin URI: https://codoplex.com
 * Description: Sell Weebly apps with Paddle Billing payments
 * Version: 1.0.0
 * Author: CODOPLEX
 * Author URI: https://codoplex.com
 * License: GPL-2.0+
 * Text Domain: wpwa-paddle
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WPWA_PADDLE_VERSION', '1.0.0');
define('WPWA_PADDLE_FILE', __FILE__);
define('WPWA_PADDLE_DIR', dirname(__FILE__));
define('WPWA_PADDLE_URL', plugin_dir_url(__FILE__));
define('WPWA_PADDLE_BASENAME', plugin_basename(__FILE__));

// Core functions
require_once WPWA_PADDLE_DIR . '/includes/helpers.php';
require_once WPWA_PADDLE_DIR . '/includes/products.php';
require_once WPWA_PADDLE_DIR . '/includes/customers.php';
require_once WPWA_PADDLE_DIR . '/includes/orders.php';
require_once WPWA_PADDLE_DIR . '/includes/subscriptions.php';
require_once WPWA_PADDLE_DIR . '/includes/access-control.php';
require_once WPWA_PADDLE_DIR . '/includes/whitelist.php';

// Paddle integration
require_once WPWA_PADDLE_DIR . '/paddle/paddle-init.php';
require_once WPWA_PADDLE_DIR . '/paddle/paddle-products.php';
require_once WPWA_PADDLE_DIR . '/paddle/paddle-customers.php';
require_once WPWA_PADDLE_DIR . '/paddle/paddle-checkout.php';
require_once WPWA_PADDLE_DIR . '/paddle/paddle-subscriptions.php';
require_once WPWA_PADDLE_DIR . '/paddle/paddle-webhook.php';

// Admin
if (is_admin()) {
    require_once WPWA_PADDLE_DIR . '/admin/admin-enqueue.php';
    require_once WPWA_PADDLE_DIR . '/admin/settings-page.php';
    require_once WPWA_PADDLE_DIR . '/admin/transactions-page.php';
    require_once WPWA_PADDLE_DIR . '/admin/subscriptions-page.php';
    require_once WPWA_PADDLE_DIR . '/admin/customers-page.php';
    require_once WPWA_PADDLE_DIR . '/admin/analytics-page.php';
    require_once WPWA_PADDLE_DIR . '/admin/whitelist-page.php';
}

// Emails
require_once WPWA_PADDLE_DIR . '/emails/confirmation.php';

// Payment flow
require_once WPWA_PADDLE_DIR . '/payments/phase-one.php';

register_activation_hook(__FILE__, 'wpwa_paddle_activate');
register_deactivation_hook(__FILE__, 'wpwa_paddle_deactivate');

function wpwa_paddle_activate() {
    wpwa_paddle_install_tables();
    wpwa_paddle_register_product_post_type();
    flush_rewrite_rules();
}

function wpwa_paddle_deactivate() {
    flush_rewrite_rules();
}

add_action('init', 'wpwa_paddle_init');
add_action('admin_menu', 'wpwa_paddle_admin_menu');

function wpwa_paddle_init() {
    wpwa_paddle_register_product_post_type();
}

add_action('parse_request', function() {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    
    if (strpos($path, 'wpwa_phase_one') === 0) {
        require_once WPWA_PADDLE_DIR . '/payments/phase-one.php';
        wpwa_paddle_handle_phase_one();
        exit;
    }
    
    if (strpos($path, 'wpwa-paddle-checkout') === 0) {
        require_once WPWA_PADDLE_DIR . '/paddle/paddle-checkout.php';
        wpwa_paddle_checkout_router();
        exit;
    }
    
    if (strpos($path, 'wpwa-paddle-webhook') === 0) {
        require_once WPWA_PADDLE_DIR . '/paddle/paddle-webhook.php';
        wpwa_paddle_handle_webhook();
        exit;
    }
}, 0);

function wpwa_paddle_admin_menu() {
    add_menu_page(
        __('Paddle Apps', 'wpwa-paddle'),
        __('Paddle Apps', 'wpwa-paddle'),
        'manage_options',
        'wpwa-paddle',
        'wpwa_paddle_render_analytics_page',
        'dashicons-cart',
        59
    );
    
    add_submenu_page('wpwa-paddle', __('Analytics', 'wpwa-paddle'), __('Analytics', 'wpwa-paddle'),
        'manage_options', 'wpwa-paddle', 'wpwa_paddle_render_analytics_page');
    
    add_submenu_page('wpwa-paddle', __('Products', 'wpwa-paddle'), __('Products', 'wpwa-paddle'),
        'manage_options', 'edit.php?post_type=wpwa_paddle_product');
    
    add_submenu_page('wpwa-paddle', __('Transactions', 'wpwa-paddle'), __('Transactions', 'wpwa-paddle'),
        'manage_options', 'wpwa-paddle-transactions', 'wpwa_paddle_render_transactions_page');
    
    add_submenu_page('wpwa-paddle', __('Subscriptions', 'wpwa-paddle'), __('Subscriptions', 'wpwa-paddle'),
        'manage_options', 'wpwa-paddle-subscriptions', 'wpwa_paddle_render_subscriptions_page');
    
    add_submenu_page('wpwa-paddle', __('Whitelist', 'wpwa-paddle'), __('Whitelist', 'wpwa-paddle'),
        'manage_options', 'wpwa-paddle-whitelist', 'wpwa_paddle_render_whitelist_page');

    add_submenu_page('wpwa-paddle', __('Customers', 'wpwa-paddle'), __('Customers', 'wpwa-paddle'),
        'manage_options', 'wpwa-paddle-customers', 'wpwa_paddle_render_customers_page');
    
    add_submenu_page('wpwa-paddle', __('Settings', 'wpwa-paddle'), __('Settings', 'wpwa-paddle'),
        'manage_options', 'wpwa-paddle-settings', 'wpwa_paddle_render_settings_page');
}