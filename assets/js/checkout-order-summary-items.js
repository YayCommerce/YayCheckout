/**
 * The Order Summary Items quantity stepper: sends one cart item's new quantity, or 0 to remove
 * it, then lets update_checkout refresh every fragment. Delegated from the block wrapper,
 * because update_order_review replaces the rows inside it.
 */
jQuery(function ($) {
    var WRAPPER = '.wp-block-yaycheckout-order-summary-items';
    // CartItemQuantity::AJAX_ACTION.
    var ENDPOINT = 'yaycheckout_cart_item_quantity';

    function wrapperOf(element) {
        var $wrapper = $(element).closest(WRAPPER);

        return $wrapper.is('[data-yaycheckout-cart-nonce]') ? $wrapper : null;
    }

    function showError($wrapper, message) {
        $('<p>', { role: 'alert', text: message || $wrapper.data('errorText') }).appendTo(
            $wrapper.find('.yaycheckout-order-summary-items__notices').empty()
        );
    }

    // Sends the target quantity, not a step, so a repeated request can't apply a change twice.
    function send($wrapper, $item, key, quantity) {
        if ($wrapper.attr('aria-busy') === 'true') {
            return;
        }

        // Only the controls PHP left enabled; the ones it disabled (− at 1, + at the maximum) stay so.
        var $controls = $item.find('button:enabled, input:enabled');

        $wrapper.attr('aria-busy', 'true');
        $controls.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', ENDPOINT),
            data: {
                security: $wrapper.data('yaycheckoutCartNonce'),
                cart_item_key: key,
                quantity: quantity,
            },
            dataType: 'json',
        })
            .done(function (response) {
                if (response && response.success) {
                    $wrapper.find('.yaycheckout-order-summary-items__notices').empty();

                    // update_order_review on an empty cart answers with a "session has expired" notice.
                    if (response.data.cart_empty) {
                        window.location = response.data.redirect;
                        return;
                    }

                    $(document.body).trigger('update_checkout').trigger('wc_fragment_refresh');
                    return;
                }

                showError($wrapper, response && response.data && response.data.message);
                $(document.body).trigger('update_checkout');
            })
            .fail(function () {
                showError($wrapper);
                $(document.body).trigger('update_checkout');
            })
            .always(function () {
                $wrapper.removeAttr('aria-busy');
                // The refresh replaces the row; until it lands, give back the controls it had.
                $controls.prop('disabled', false);
            });
    }

    function commitInput(input) {
        var $wrapper = wrapperOf(input);
        var $input = $(input);
        var current = parseInt($input.attr('value'), 10);
        var value = $input.val();
        var quantity = Number(value);

        if (!$wrapper) {
            return;
        }

        if (!/^\d+$/.test(String(value).trim()) || quantity < 1) {
            $input.val(current);
            return;
        }

        if (quantity !== current) {
            send($wrapper, $input.closest('.yaycheckout-order-summary-items__item'), $input.data('cartItemKey'), quantity);
        }
    }

    $(document.body)
        .on('click', WRAPPER + ' .yaycheckout-qty__button', function () {
            var $wrapper = wrapperOf(this);
            var $input = $(this).closest('.yaycheckout-qty').find('.yaycheckout-qty__input');

            if (!$wrapper || !$input.length) {
                return;
            }

            send(
                $wrapper,
                $(this).closest('.yaycheckout-order-summary-items__item'),
                $input.data('cartItemKey'),
                parseInt($input.attr('value'), 10) + parseInt($(this).data('step'), 10)
            );
        })
        .on('change', WRAPPER + ' .yaycheckout-qty__input', function () {
            commitInput(this);
        })
        .on('keydown', WRAPPER + ' .yaycheckout-qty__input', function (event) {
            // Inside the checkout form, Enter would otherwise submit it and place the order.
            if (event.key === 'Enter' && !(event.originalEvent && event.originalEvent.isComposing)) {
                event.preventDefault();
                commitInput(this);
            }
        })
        .on('click', WRAPPER + ' .yaycheckout-order-summary-items__remove', function (event) {
            var $wrapper = wrapperOf(this);

            if (!$wrapper) {
                return;
            }

            event.preventDefault();
            send($wrapper, $(this).closest('.yaycheckout-order-summary-items__item'), $(this).data('cartItemKey'), 0);
        });
});
