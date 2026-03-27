<?php
if (!defined('ABSPATH')) exit;

function wpwa_paddle_send_confirmation_email($transaction_id) {
    $transaction = wpwa_paddle_get_transaction($transaction_id);
    
    if (!$transaction) {
        return false;
    }
    
    $product = wpwa_paddle_get_product($transaction['product_id']);
    $customer = wpwa_paddle_get_customer_by_weebly_id($transaction['weebly_user_id']);
    
    if (!$product || !$customer) {
        return false;
    }
    
    $to = $customer['email'];
    $subject = sprintf('[%s] Payment Confirmation - Order #%d', get_bloginfo('name'), $transaction_id);
    
    $message = wpwa_paddle_get_confirmation_email_html(array(
        'transaction' => $transaction,
        'product' => $product,
        'customer' => $customer
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Codoplex <sales@codoplex.com>',
        'Cc: junaidte14@gmail.com'
    );
    
    return wp_mail($to, $subject, $message, $headers);
}

function wpwa_paddle_get_confirmation_email_html($data) {
    $transaction = $data['transaction'];
    $product = $data['product'];
    $customer = $data['customer'];
    
    $site_name = get_bloginfo('name');
    $date = date('F j, Y', strtotime($transaction['created_at']));
    
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f7fa;">
    <div class="email-wrapper" style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);">
        
        <div class="header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 40px 40px 35px; text-align: center;">
            <h1 style="margin: 0 0 8px; font-size: 32px; font-weight: 700; line-height: 1.2; color: #ffffff;">✓ Payment Received!</h1>
            <p style="margin: 0; font-size: 16px; opacity: 0.95; color: #ffffff;">Thank you for your purchase</p>
        </div>
        
        <div class="content" style="padding: 35px 40px 25px;">
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">Dear <strong><?php echo esc_html($customer['name'] ?: 'Valued Customer'); ?></strong>,</p>
            
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">Thank you for your purchase! Your payment has been successfully processed and <strong><?php echo esc_html($product['name']); ?></strong> is now active on your Weebly account.</p>
            
            <div style="background: linear-gradient(135deg, #f6f8fc 0%, #e9ecf5 100%); padding: 20px 25px; border-radius: 10px; margin: 20px 0 30px; border: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 15px; font-size: 18px; font-weight: 700; color: #1a202c;">📋 Order Summary</h3>
                
                <div style="display: table; width: 100%;">
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Order Number</div>
                        <div style="display: table-cell; padding: 6px 0; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;">#<?php echo $transaction['id']; ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Date</div>
                        <div style="display: table-cell; padding: 6px 0; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;"><?php echo $date; ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Product</div>
                        <div style="display: table-cell; padding: 6px 0; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;"><?php echo esc_html($product['name']); ?></div>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #e2e8f0; text-align: right;">
                    <div style="font-size: 13px; color: #718096; margin-bottom: 5px;">Total Paid</div>
                    <div style="font-size: 28px; font-weight: 900; color: #667eea;"><?php echo wpwa_paddle_format_price($transaction['amount']); ?></div>
                </div>
            </div>
            
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;"><strong>What's Next?</strong></p>
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">Your app is ready to use! Access it from your Weebly dashboard and start building amazing websites.</p>
        </div>
        
        <div style="height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 0 40px;"></div>
        
        <div class="content" style="padding: 30px 40px 25px;">
            <div style="background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%); border-radius: 10px; border: 1px solid #90cdf4; padding: 20px;">
                <div style="display: table; width: 100%;">
                    <div style="display: table-cell; width: 60px; text-align: center; vertical-align: middle; font-size: 40px;">💬</div>
                    <div style="display: table-cell; vertical-align: middle; padding-left: 15px;">
                        <h3 style="margin: 0 0 5px; font-size: 16px; font-weight: 700; color: #1a202c;">Need Help Getting Started?</h3>
                        <p style="margin: 0 0 10px; font-size: 13px; color: #4a5568; line-height: 1.5;">I'm here to help with installation, setup, or any questions you have about your app.</p>
                        <a href="https://codoplex.com/contact" style="display: inline-block; padding: 8px 18px; background-color: #3182ce; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px;">Contact Support →</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content" style="padding: 0 40px 35px;">
            <div style="background-color: #f7fafc; border-left: 4px solid #667eea; padding: 15px 18px; border-radius: 6px;">
                <p style="margin: 0 0 8px; font-size: 14px; color: #2d3748; line-height: 1.5; font-style: italic;">"Thank you for choosing <?php echo esc_html($product['name']); ?>. As the developer, I'm personally committed to ensuring you have the best experience possible!"</p>
                <p style="margin: 0; font-size: 13px; color: #4a5568; font-weight: 600; font-style: normal;">— Junaid Hassan, Weebly Apps Developer</p>
            </div>
        </div>
        
        <div style="padding: 25px 40px; background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); text-align: center;">
            <p style="margin: 0 0 12px; font-size: 14px; color: #ffffff; font-weight: 700;">Codoplex</p>
            <p style="margin: 0 0 8px; font-size: 12px; color: #a0aec0; line-height: 1.5;">Premium Weebly apps built by Junaid Hassan</p>
            <p style="margin: 0 0 8px; font-size: 12px; color: #a0aec0; line-height: 1.5;">
                <a href="https://codoplex.com" style="color: #90cdf4; text-decoration: none; margin: 0 8px;">Visit Website</a> |
                <a href="https://codoplex.com/contact" style="color: #90cdf4; text-decoration: none; margin: 0 8px;">Support</a>
            </p>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <p style="margin: 0; font-size: 11px; color: #718096; line-height: 1.5;">
                    © <?php echo date('Y'); ?> Codoplex. All rights reserved.<br>
                    Sent to <?php echo esc_html($customer['email']); ?>
                </p>
            </div>
        </div>
        
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}