<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $sql_customers = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_paddle_customers` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `paddle_customer_id` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) NOT NULL,
      `email` VARCHAR(255) NOT NULL,
      `name` VARCHAR(255) DEFAULT NULL,
      `metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_paddle_customer` (`paddle_customer_id`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_email` (`email`)
    ) $charset_collate;";
    
    dbDelta($sql_customers);
    
    $sql_transactions = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_paddle_transactions` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `transaction_type` VARCHAR(20) NOT NULL,
      `paddle_transaction_id` VARCHAR(255) DEFAULT NULL,
      `paddle_subscription_id` VARCHAR(255) DEFAULT NULL,
      `paddle_customer_id` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) NOT NULL,
      `weebly_site_id` VARCHAR(255) DEFAULT NULL,
      `product_id` BIGINT(20) UNSIGNED NOT NULL,
      `amount` DECIMAL(10,2) NOT NULL,
      `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
      `status` VARCHAR(50) NOT NULL,
      `access_token` TEXT DEFAULT NULL,
      `final_url` TEXT DEFAULT NULL,
      `weebly_notified` TINYINT(1) NOT NULL DEFAULT 0,
      `metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_paddle_transaction` (`paddle_transaction_id`),
      KEY `idx_paddle_subscription` (`paddle_subscription_id`),
      KEY `idx_customer` (`paddle_customer_id`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_product` (`product_id`),
      KEY `idx_status` (`status`)
    ) $charset_collate;";
    
    dbDelta($sql_transactions);
    
    $sql_subscriptions = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_paddle_subscriptions` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `paddle_subscription_id` VARCHAR(255) NOT NULL,
      `paddle_customer_id` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) NOT NULL,
      `weebly_site_id` VARCHAR(255) DEFAULT NULL,
      `product_id` BIGINT(20) UNSIGNED NOT NULL,
      `paddle_price_id` VARCHAR(255) NOT NULL,
      `status` VARCHAR(50) NOT NULL,
      `current_period_start` DATETIME NOT NULL,
      `current_period_end` DATETIME NOT NULL,
      `scheduled_change` VARCHAR(50) DEFAULT NULL,
      `access_token` TEXT DEFAULT NULL,
      `metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_paddle_subscription` (`paddle_subscription_id`),
      KEY `idx_customer` (`paddle_customer_id`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_product` (`product_id`),
      KEY `idx_status` (`status`)
    ) $charset_collate;";
    
    dbDelta($sql_subscriptions);
    
    $sql_webhook = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_paddle_webhook_log` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `event_id` VARCHAR(255) NOT NULL,
      `event_type` VARCHAR(100) NOT NULL,
      `payload` LONGTEXT NOT NULL,
      `processed` TINYINT(1) NOT NULL DEFAULT 0,
      `error` TEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_event_id` (`event_id`),
      KEY `idx_event_type` (`event_type`),
      KEY `idx_processed` (`processed`)
    ) $charset_collate;";
    
    dbDelta($sql_webhook);
    
    update_option('wpwa_paddle_db_version', WPWA_PADDLE_VERSION);
}

function wpwa_paddle_get_option($key, $default = '') {
    return get_option('wpwa_paddle_' . $key, $default);
}

function wpwa_paddle_update_option($key, $value) {
    return update_option('wpwa_paddle_' . $key, $value);
}

function wpwa_paddle_is_enabled() {
    return wpwa_paddle_get_option('enabled') === 'yes';
}

function wpwa_paddle_is_sandbox_mode() {
    return wpwa_paddle_get_option('sandbox_mode') === 'yes';
}

function wpwa_paddle_get_api_key() {
    if (wpwa_paddle_is_sandbox_mode()) {
        return wpwa_paddle_get_option('sandbox_api_key');
    }
    return wpwa_paddle_get_option('live_api_key');
}

function wpwa_paddle_get_client_token() {
    if (wpwa_paddle_is_sandbox_mode()) {
        return wpwa_paddle_get_option('sandbox_client_token');
    }
    return wpwa_paddle_get_option('live_client_token');
}

function wpwa_paddle_log($message, $context = array()) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WPWA Paddle] ' . $message . ' ' . print_r($context, true));
    }
}

function wpwa_paddle_format_price($amount, $currency = 'USD') {
    return '$' . number_format($amount, 2);
}

function wpwa_paddle_encrypt_token($plain) {
    if (empty($plain)) return '';
    
    $key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    $iv = substr(hash('sha256', SECURE_AUTH_SALT), 0, 16);
    $cipher = 'aes-256-ctr';
    
    $encrypted = openssl_encrypt($plain, $cipher, $key, 0, $iv);
    return $encrypted ? base64_encode($encrypted) : '';
}

function wpwa_paddle_decrypt_token($encrypted) {
    if (empty($encrypted)) return '';
    
    $key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    $iv = substr(hash('sha256', SECURE_AUTH_SALT), 0, 16);
    $cipher = 'aes-256-ctr';
    
    $decrypted = openssl_decrypt(base64_decode($encrypted), $cipher, $key, 0, $iv);
    return $decrypted ?: '';
}