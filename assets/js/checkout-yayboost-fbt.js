/**
 * YayBoost's Frequently bought together in a layout spot (templates/yayboost-fbt.php). Add puts a product in the
 * cart through YayBoost's own add-to-cart action, so the order keeps its attribution; Added removes it through
 * CartItemQuantity. Either way update_checkout then reprints the spot from the cart (YayBoost::spot_fragments()).
 * Delegated, because that reprint replaces the buttons.
 */
jQuery(function ($) {
    var WRAPPER = '.yaycheckout-fbt';
    // CartItemQuantity::AJAX_ACTION.
    var REMOVE_ENDPOINT = 'yaycheckout_cart_item_quantity';
    var leaving = false;

    function request($wrapper, $button) {
        var key = $button.attr('data-cart-item-key');

        if (key) {
            return {
                url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', REMOVE_ENDPOINT),
                data: { security: $wrapper.data('cartNonce'), cart_item_key: key, quantity: 0 },
            };
        }

        return {
            url: $wrapper.data('ajaxUrl'),
            data: {
                action: $wrapper.data('addAction'),
                nonce: $wrapper.data('addNonce'),
                product_ids: [$button.attr('data-product-id')],
                source_product_id: $button.attr('data-source-product-id'),
            },
        };
    }

    function showError($wrapper, message) {
        $wrapper.find('.yaycheckout-fbt__notice').remove();
        $('<p>', { class: 'yaycheckout-fbt__notice', role: 'alert', text: message || $wrapper.data('errorText') }).appendTo($wrapper);
    }

    $(document.body).on('click', WRAPPER + ' .yaycheckout-fbt__button', function () {
        var $button = $(this);
        var $wrapper = $button.closest(WRAPPER);
        var options = request($wrapper, $button);

        if (leaving || $button.prop('disabled')) {
            return;
        }

        $wrapper.find('.yaycheckout-fbt__notice').remove();
        $button.prop('disabled', true).attr('aria-busy', 'true');

        $.ajax({ type: 'POST', url: options.url, data: options.data, dataType: 'json' })
            .done(function (response) {
                if (!response || !response.success) {
                    $button.prop('disabled', false).removeAttr('aria-busy');
                    showError($wrapper, response && response.data && response.data.message);
                    return;
                }

                // update_order_review on an empty cart answers with a "session has expired" notice.
                if (response.data && response.data.cart_empty) {
                    leaving = true;
                    window.location = response.data.redirect;
                    return;
                }

                // The reprint replaces the button, so it stays disabled until then.
                $(document.body).trigger('update_checkout').trigger('wc_fragment_refresh');
            })
            .fail(function (xhr) {
                var data = xhr.responseJSON && xhr.responseJSON.data;

                $button.prop('disabled', false).removeAttr('aria-busy');
                showError($wrapper, data && data.message);
            });
    });
});
