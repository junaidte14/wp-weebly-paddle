<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_sync_product_to_paddle($product_id) {
    $product = wpwa_paddle_get_product($product_id);
    if (!$product) {
        return array('success' => false, 'message' => 'Product not found');
    }
    
    try {
        $paddle_product_id = $product['paddle_product_id'];
        
        if ($paddle_product_id) {
            $paddle_product = wpwa_paddle_update_product($product, $paddle_product_id);
        } else {
            $paddle_product = wpwa_paddle_create_product($product);
            update_post_meta($product_id, '_wpwa_paddle_product_id', $paddle_product['id']);
        }
        
        $price = wpwa_paddle_sync_product_price($product, $paddle_product['id']);
        update_post_meta($product_id, '_wpwa_paddle_price_id', $price['id']);
        
        delete_post_meta($product_id, '_wpwa_paddle_needs_resync');
        
        return array(
            'success' => true,
            'product_id' => $paddle_product['id'],
            'price_id' => $price['id']
        );
        
    } catch (Exception $e) {
        wpwa_paddle_log('Product sync error: ' . $e->getMessage(), array('product_id' => $product_id));
        return array('success' => false, 'message' => $e->getMessage());
    }
}

function wpwa_paddle_create_product($product) {
    $data = array(
        'name' => $product['name'],
        'description' => wp_strip_all_tags($product['description']),
        'tax_category' => 'standard',
        'custom_data' => array(
            'wp_product_id' => $product['id']
        )
    );
    
    $result = wpwa_paddle_api_request('/products', 'POST', $data);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    return $result['data'];
}

function wpwa_paddle_update_product($product, $paddle_product_id) {
    $data = array(
        'name' => $product['name'],
        'description' => wp_strip_all_tags($product['description']),
        'custom_data' => array(
            'wp_product_id' => $product['id']
        )
    );
    
    $result = wpwa_paddle_api_request('/products/' . $paddle_product_id, 'PATCH', $data);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    return $result['data'];
}

function wpwa_paddle_sync_product_price($product, $paddle_product_id) {
    $existing_price_id = $product['paddle_price_id'];
    
    $unit_amount = strval(intval($product['price'] * 100));
    
    if ($existing_price_id) {
        $result = wpwa_paddle_api_request('/prices/' . $existing_price_id, 'GET');
        
        if ($result['success']) {
            $existing_price = $result['data'];
            $matches = ($existing_price['unit_price']['amount'] === $unit_amount);
            
            if ($product['is_recurring']) {
                $billing_cycle = array(
                    'interval' => $product['billing_cycle'],
                    'frequency' => $product['billing_frequency']
                );
                
                if (!isset($existing_price['billing_cycle']) || 
                    $existing_price['billing_cycle'] != $billing_cycle) {
                    $matches = false;
                }
            }
            
            if ($matches && $existing_price['status'] === 'active') {
                return $existing_price;
            }
        }
    }
    
    $price_data = array(
        'product_id' => $paddle_product_id,
        'description' => $product['name'] . ' - Price',
        'unit_price' => array(
            'amount' => $unit_amount,
            'currency_code' => 'USD'
        ),
        'custom_data' => array(
            'wp_product_id' => $product['id']
        )
    );
    
    if ($product['is_recurring']) {
        $price_data['billing_cycle'] = array(
            'interval' => $product['billing_cycle'],
            'frequency' => $product['billing_frequency']
        );
    }
    
    if ($existing_price_id) {
        wpwa_paddle_api_request('/prices/' . $existing_price_id, 'PATCH', array('status' => 'archived'));
    }
    
    $result = wpwa_paddle_api_request('/prices', 'POST', $price_data);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    return $result['data'];
}

function wpwa_paddle_get_or_sync_product($product_id) {
    $product = wpwa_paddle_get_product($product_id);
    
    if (!$product) {
        return null;
    }
    
    $needs_resync = get_post_meta($product_id, '_wpwa_paddle_needs_resync', true);
    
    if (!$product['paddle_product_id'] || $needs_resync) {
        $result = wpwa_paddle_sync_product_to_paddle($product_id);
        
        if (!$result['success']) {
            wpwa_paddle_log('Auto-sync failed for product: ' . $product_id);
            return null;
        }
        
        $product = wpwa_paddle_get_product($product_id);
    }
    
    return $product;
}

add_action('wp_ajax_wpwa_paddle_sync_product', 'wpwa_paddle_ajax_sync_product');
function wpwa_paddle_ajax_sync_product() {
    check_ajax_referer('wpwa_paddle_sync', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    $product_id = absint($_POST['product_id']);
    $result = wpwa_paddle_sync_product_to_paddle($product_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}