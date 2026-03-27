(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        $('.wpwa-paddle-sync-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var productId = $btn.data('product-id');
            
            if (!confirm('Sync this product to Paddle?')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Syncing...');
            
            $.post(wppaPaddle.ajaxurl, {
                action: 'wpwa_paddle_sync_product',
                nonce: wppaPaddle.nonce,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    alert('Product synced successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text('Sync to Paddle');
                }
            }).fail(function() {
                alert('Request failed. Please try again.');
                $btn.prop('disabled', false).text('Sync to Paddle');
            });
        });
        
        $('#wpwa_paddle_is_recurring').on('change', function() {
            if ($(this).is(':checked')) {
                $('#wpwa_paddle_recurring_fields').slideDown();
            } else {
                $('#wpwa_paddle_recurring_fields').slideUp();
            }
        }).trigger('change');
        
    });
    
})(jQuery);