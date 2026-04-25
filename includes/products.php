<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_register_product_post_type() {
    register_post_type('wpwa_paddle_product', array(
        'labels' => array(
            'name' => __('Paddle Products', 'wpwa-paddle'),
            'singular_name' => __('Product', 'wpwa-paddle'),
            'add_new' => __('Add New Product', 'wpwa-paddle'),
            'add_new_item' => __('Add New Product', 'wpwa-paddle'),
            'edit_item' => __('Edit Product', 'wpwa-paddle'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => array('title', 'editor', 'thumbnail'),
        'has_archive' => false,
        'rewrite' => false
    ));
}

add_action('add_meta_boxes_wpwa_paddle_product', 'wpwa_paddle_add_product_meta_boxes');
function wpwa_paddle_add_product_meta_boxes() {
    add_meta_box('wpwa_paddle_product_details', __('Product Details', 'wpwa-paddle'),
        'wpwa_paddle_render_product_details_meta_box', 'wpwa_paddle_product', 'normal', 'high');
    
    add_meta_box('wpwa_paddle_product_weebly', __('Weebly Configuration', 'wpwa-paddle'),
        'wpwa_paddle_render_product_weebly_meta_box', 'wpwa_paddle_product', 'normal', 'high');
    
    add_meta_box('wpwa_paddle_product_sync', __('Paddle Sync', 'wpwa-paddle'),
        'wpwa_paddle_render_product_sync_meta_box', 'wpwa_paddle_product', 'side', 'default');
}

function wpwa_paddle_render_product_details_meta_box($post) {
    wp_nonce_field('wpwa_paddle_product_details', 'wpwa_paddle_product_details_nonce');
    
    $price = get_post_meta($post->ID, '_wpwa_paddle_price', true);
    $is_recurring = get_post_meta($post->ID, '_wpwa_paddle_is_recurring', true);
    $billing_cycle = get_post_meta($post->ID, '_wpwa_paddle_billing_cycle', true) ?: 'month';
    $billing_frequency = get_post_meta($post->ID, '_wpwa_paddle_billing_frequency', true) ?: 1;
    ?>
    <table class="form-table">
        <tr>
            <th><label for="wpwa_paddle_price">Price (USD)</label></th>
            <td>
                <input type="number" step="0.01" min="0" id="wpwa_paddle_price" name="wpwa_paddle_price" 
                       value="<?php echo esc_attr($price); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_paddle_is_recurring">Recurring Subscription</label></th>
            <td>
                <label>
                    <input type="checkbox" id="wpwa_paddle_is_recurring" name="wpwa_paddle_is_recurring" 
                           value="1" <?php checked($is_recurring, '1'); ?>>
                    Enable recurring billing
                </label>
            </td>
        </tr>
    </table>
    
    <div id="wpwa_paddle_recurring_fields" style="<?php echo $is_recurring ? '' : 'display:none;'; ?>">
        <hr>
        <h4>Recurring Settings</h4>
        <table class="form-table">
            <tr>
                <th><label for="wpwa_paddle_billing_frequency">Billing Cycle</label></th>
                <td>
                    <input type="number" min="1" id="wpwa_paddle_billing_frequency" name="wpwa_paddle_billing_frequency" 
                           value="<?php echo esc_attr($billing_frequency); ?>" style="width: 80px;"> 
                    <select name="wpwa_paddle_billing_cycle">
                        <option value="day" <?php selected($billing_cycle, 'day'); ?>>Day(s)</option>
                        <option value="week" <?php selected($billing_cycle, 'week'); ?>>Week(s)</option>
                        <option value="month" <?php selected($billing_cycle, 'month'); ?>>Month(s)</option>
                        <option value="year" <?php selected($billing_cycle, 'year'); ?>>Year(s)</option>
                    </select>
                </td>
            </tr>
        </table>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#wpwa_paddle_is_recurring').on('change', function() {
            $('#wpwa_paddle_recurring_fields').toggle(this.checked);
        });
    });
    </script>
    <?php
}

function wpwa_paddle_render_product_weebly_meta_box($post) {
    wp_nonce_field('wpwa_paddle_product_weebly', 'wpwa_paddle_product_weebly_nonce');
    
    $client_id = get_post_meta($post->ID, '_wpwa_paddle_client_id', true);
    $client_secret = get_post_meta($post->ID, '_wpwa_paddle_client_secret', true);
    $callback_url = home_url('/wpwa_paddle_phase_one/?pr_id=' . $post->ID);
    $old_pr_id = get_post_meta($post->ID, '_wpwa_paddle_old_pr_id', true);
    $app_url = get_post_meta($post->ID, '_wpwa_paddle_app_url', true);
    ?>
    <table class="form-table">
        <tr>
            <th><label for="wpwa_paddle_old_pr_id">Old Product ID</label></th>
            <td>
                <input type="text" id="wpwa_paddle_old_pr_id" name="wpwa_paddle_old_pr_id" 
                       value="<?php echo esc_attr($old_pr_id); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_paddle_client_id">Weebly Client ID</label></th>
            <td>
                <input type="text" id="wpwa_paddle_client_id" name="wpwa_paddle_client_id" 
                       value="<?php echo esc_attr($client_id); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_paddle_client_secret">Weebly Client Secret</label></th>
            <td>
                <input type="text" id="wpwa_paddle_client_secret" name="wpwa_paddle_client_secret" 
                       value="<?php echo esc_attr($client_secret); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th>Callback URL</th>
            <td>
                <input type="text" value="<?php echo esc_url($callback_url); ?>" 
                       class="regular-text" readonly onclick="this.select();">
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_paddle_app_url">Weebly App Center URL</label></th>
            <td>
                <input type="text" id="wpwa_paddle_app_url" name="wpwa_paddle_app_url" 
                       value="<?php echo esc_attr($app_url); ?>" class="regular-text">
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Render Paddle manual input meta box
 */
function wpwa_paddle_render_product_sync_meta_box($post) {
    // Retrieve current values
    $paddle_product_id = get_post_meta($post->ID, '_wpwa_paddle_product_id', true);
    $paddle_price_id = get_post_meta($post->ID, '_wpwa_paddle_price_id', true);
    $stripe_product_id = get_post_meta($post->ID, '_wpwa_stripe_product_id', true);
    $stripe_price_id = get_post_meta($post->ID, '_wpwa_stripe_price_id', true);
    
   ?>
    <div style="padding: 10px;">
        <p>
            <label for="wpwa_paddle_product_id"><strong>Paddle Product ID</strong></label><br />
            <input type="text" id="wpwa_paddle_product_id" name="wpwa_paddle_product_id" 
                   value="<?php echo esc_attr($paddle_product_id); ?>" class="widefat" placeholder="pro_..." />
        </p>
        <p>
            <label for="wpwa_paddle_price_id"><strong>Paddle Price ID</strong></label><br />
            <input type="text" id="wpwa_paddle_price_id" name="wpwa_paddle_price_id" 
                   value="<?php echo esc_attr($paddle_price_id); ?>" class="widefat" placeholder="pri_..." />
        </p>
        <p class="description">
            Enter the Product and Price IDs from your Paddle Billing dashboard.
        </p>
    </div>

    <div style="padding: 10px;">
        <p>
            <label for="wpwa_stripe_product_id"><strong>Stripe Product ID</strong></label><br />
            <input type="text" id="wpwa_stripe_product_id" name="wpwa_stripe_product_id" 
                   value="<?php echo esc_attr($stripe_product_id); ?>" class="widefat" placeholder="pro_..." />
        </p>
        <p>
            <label for="wpwa_stripe_price_id"><strong>Stripe Price ID</strong></label><br />
            <input type="text" id="wpwa_stripe_price_id" name="wpwa_stripe_price_id" 
                   value="<?php echo esc_attr($stripe_price_id); ?>" class="widefat" placeholder="pri_..." />
        </p>
        <p class="description">
            Enter the Product and Price IDs from your Stripe dashboard.
        </p>
    </div>
    <?php
}

add_action('save_post_wpwa_paddle_product', 'wpwa_paddle_save_product_meta', 10, 2);
function wpwa_paddle_save_product_meta($post_id, $post) {
    if (!isset($_POST['wpwa_paddle_product_details_nonce']) || 
        !wp_verify_nonce($_POST['wpwa_paddle_product_details_nonce'], 'wpwa_paddle_product_details')) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    update_post_meta($post_id, '_wpwa_paddle_price', sanitize_text_field($_POST['wpwa_paddle_price']));
    update_post_meta($post_id, '_wpwa_paddle_is_recurring', isset($_POST['wpwa_paddle_is_recurring']) ? '1' : '0');
    
    if (isset($_POST['wpwa_paddle_is_recurring'])) {
        update_post_meta($post_id, '_wpwa_paddle_billing_frequency', absint($_POST['wpwa_paddle_billing_frequency']));
        update_post_meta($post_id, '_wpwa_paddle_billing_cycle', sanitize_text_field($_POST['wpwa_paddle_billing_cycle']));
    }
    
    update_post_meta($post_id, '_wpwa_paddle_client_id', sanitize_text_field($_POST['wpwa_paddle_client_id']));
    update_post_meta($post_id, '_wpwa_paddle_client_secret', sanitize_text_field($_POST['wpwa_paddle_client_secret']));
    update_post_meta($post_id, '_wpwa_paddle_old_pr_id', sanitize_text_field($_POST['wpwa_paddle_old_pr_id']));
    update_post_meta($post_id, '_wpwa_paddle_app_url', sanitize_text_field($_POST['wpwa_paddle_app_url']));
    
    // --- PADDLE SAVING ---
    if (isset($_POST['wpwa_paddle_product_id'])) {
        update_post_meta($post_id, '_wpwa_paddle_product_id', sanitize_text_field($_POST['wpwa_paddle_product_id']));
    }
    if (isset($_POST['wpwa_paddle_price_id'])) {
        update_post_meta($post_id, '_wpwa_paddle_price_id', sanitize_text_field($_POST['wpwa_paddle_price_id']));
    }

    // --- Stripe SAVING ---
    if (isset($_POST['wpwa_stripe_product_id'])) {
        update_post_meta($post_id, '_wpwa_stripe_product_id', sanitize_text_field($_POST['wpwa_stripe_product_id']));
    }
    if (isset($_POST['wpwa_stripe_price_id'])) {
        update_post_meta($post_id, '_wpwa_stripe_price_id', sanitize_text_field($_POST['wpwa_stripe_price_id']));
    }
    
}

function wpwa_paddle_get_product($product_id) {
    $post = get_post($product_id);
    
    if (!$post || $post->post_type !== 'wpwa_paddle_product') {
        $args = array(
            'post_type' => 'wpwa_paddle_product',
            'meta_query' => array(
                array('key' => '_wpwa_paddle_old_pr_id', 'value' => $product_id)
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $found_id = $query->posts[0];
            $post = get_post($found_id);
            $pr_id = get_post_meta($post->ID, '_wpwa_paddle_old_pr_id', true);
        } else {
            return null;
        }
    } else {
        $pr_id = $post->ID;
    }
    
    if (!$post || $post->post_type !== 'wpwa_paddle_product') {
        return null;
    }
    
    return array(
        'id' => $post->ID,
        'name' => $post->post_title,
        'description' => $post->post_content,
        'price' => floatval(get_post_meta($post->ID, '_wpwa_paddle_price', true)),
        'is_recurring' => get_post_meta($post->ID, '_wpwa_paddle_is_recurring', true) === '1',
        'billing_frequency' => absint(get_post_meta($post->ID, '_wpwa_paddle_billing_frequency', true)),
        'billing_cycle' => get_post_meta($post->ID, '_wpwa_paddle_billing_cycle', true),
        'client_id' => get_post_meta($post->ID, '_wpwa_paddle_client_id', true),
        'client_secret' => get_post_meta($post->ID, '_wpwa_paddle_client_secret', true),
        'paddle_product_id' => get_post_meta($post->ID, '_wpwa_paddle_product_id', true),
        'paddle_price_id' => get_post_meta($post->ID, '_wpwa_paddle_price_id', true),
        'pr_id' => $pr_id
    );
}

function wpwa_paddle_get_all_products() {
    $posts = get_posts(array(
        'post_type' => 'wpwa_paddle_product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    $products = array();
    foreach ($posts as $post) {
        $products[] = wpwa_paddle_get_product($post->ID);
    }
    
    return $products;
}
