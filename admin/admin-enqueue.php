<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', 'wpwa_paddle_enqueue_admin_assets');
function wpwa_paddle_enqueue_admin_assets($hook) {
    $our_pages = array(
        'toplevel_page_wpwa-paddle',
        'paddle-apps_page_wpwa-paddle-transactions',
        'paddle-apps_page_wpwa-paddle-subscriptions',
        'paddle-apps_page_wpwa-paddle-customers',
        'paddle-apps_page_wpwa-paddle-settings'
    );
    
    if (!in_array($hook, $our_pages) && get_post_type() !== 'wpwa_paddle_product') {
        return;
    }
    
    wp_enqueue_style(
        'wpwa-paddle-admin',
        WPWA_PADDLE_URL . 'admin/css/admin.css',
        array(),
        WPWA_PADDLE_VERSION
    );
    
    wp_enqueue_script(
        'wpwa-paddle-admin',
        WPWA_PADDLE_URL . 'admin/js/admin.js',
        array('jquery'),
        WPWA_PADDLE_VERSION,
        true
    );
    
    wp_localize_script('wpwa-paddle-admin', 'wppaPaddle', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpwa_paddle_admin')
    ));
}