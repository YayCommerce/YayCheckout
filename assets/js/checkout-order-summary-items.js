/**
 * The Order Summary Items quantity stepper: sends one cart item's new quantity, or 0 to remove
 * it, then lets update_checkout refresh every fragment. Delegated from the block wrapper,
 * because update_order_review replaces the rows inside it.
 *
 * A click shows its quantity at once and waits for the clicks after it, so a run of clicks
 * sends one request with the last quantity.
 */
jQuery(function ($) {
    var WRAPPER = '.wp-block-yaycheckout-order-summary-items[data-yaycheckout-cart-nonce]';
    // CartItemQuantity::AJAX_ACTION.
    var ENDPOINT = 'yaycheckout_cart_item_quantity';
    // How long after the last click the stepper sends.
    var DELAY = 400;

    // The quantities the shopper picked that the cart doesn't hold yet, by cart item key.
    var wanted = {};
    // The keys still waiting out DELAY.
    var timers = {};
    var sending = false;
    var leaving = false;

    function inputFor(key) {
        return $(WRAPPER + ' .yaycheckout-qty__input').filter(function () {
            return $(this).attr('data-cart-item-key') === key;
        });
    }

    // Shows a quantity in a row's stepper, with − and + disabled at the limits PHP disables them at.
    function show($input, quantity) {
        var max = parseInt($input.attr('max'), 10);
        var $stepper = $input.closest('.yaycheckout-qty');

        $input.val(quantity);
        $stepper.find('[data-step="-1"]').prop('disabled', quantity <= 1);
        $stepper.find('[data-step="1"]').prop('disabled', max > 0 && quantity >= max);
    }

    function showError(message) {
        var $wrapper = $(WRAPPER);

        $('<p>', { role: 'alert', text: message || $wrapper.data('errorText') }).appendTo(
            $wrapper.find('.yaycheckout-order-summary-items__notices').empty()
        );
    }

    function ready() {
        return Object.keys(wanted).filter(function (key) {
            return !timers[key];
        });
    }

    // One request at a time, since each one saves the whole cart session; the refresh waits for the last.
    function flush() {
        var $wrapper = $(WRAPPER);
        var key = ready()[0];

        if (sending || leaving || key === undefined) {
            return;
        }

        var quantity = wanted[key];

        // Sends the target quantity, not a step, so a repeated request can't apply a change twice.
        sending = true;
        $wrapper.attr('aria-busy', 'true');

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
                        leaving = true;
                        window.location = response.data.redirect;
                    }

                    return;
                }

                showError(response && response.data && response.data.message);
            })
            .fail(function () {
                showError();
            })
            .always(function () {
                sending = false;
                $wrapper.removeAttr('aria-busy');

                // Applied or refused, the refresh shows what the cart holds, unless the shopper has picked again since.
                if (wanted[key] === quantity) {
                    delete wanted[key];
                }

                if (leaving) {
                    return;
                }

                if (ready().length) {
                    flush();
                    return;
                }

                $(document.body).trigger('update_checkout').trigger('wc_fragment_refresh');
            });
    }

    function pick(key, quantity, delay) {
        clearTimeout(timers[key]);
        delete timers[key];
        wanted[key] = quantity;

        if (delay) {
            timers[key] = setTimeout(function () {
                delete timers[key];
                flush();
            }, delay);
        }

        flush();
    }

    function commitInput(input) {
        var $input = $(input);
        var key = $input.attr('data-cart-item-key');
        var shown = key in wanted ? wanted[key] : parseInt($input.attr('value'), 10);
        var value = $input.val();
        var quantity = Number(value);

        if (!$input.closest(WRAPPER).length) {
            return;
        }

        if (!/^\d+$/.test(String(value).trim()) || quantity < 1) {
            show($input, shown);
            return;
        }

        if (quantity !== shown) {
            pick(key, quantity, 0);
        }
    }

    $(document.body)
        .on('click', WRAPPER + ' .yaycheckout-qty__button', function () {
            var $input = $(this).closest('.yaycheckout-qty').find('.yaycheckout-qty__input');
            var key = $input.attr('data-cart-item-key');

            if (!key) {
                return;
            }

            var current = key in wanted ? wanted[key] : parseInt($input.attr('value'), 10);
            var max = parseInt($input.attr('max'), 10);
            var quantity = current + parseInt($(this).data('step'), 10);

            if (quantity < 1 || (max > 0 && quantity > max)) {
                return;
            }

            show($input, quantity);
            pick(key, quantity, DELAY);
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
            event.preventDefault();
            pick($(this).attr('data-cart-item-key'), 0, 0);
        })
        // The refresh prints what the cart holds; keep showing what the shopper picked until it's sent.
        .on('updated_checkout', function () {
            $.each(wanted, function (key, quantity) {
                if (quantity > 0) {
                    show(inputFor(key), quantity);
                }
            });
        });

    // An order placed before a picked quantity reaches the cart would charge the old one: send it now and hold the order.
    $('form.checkout').on('checkout_place_order', function () {
        if (!sending && !Object.keys(wanted).length) {
            return true;
        }

        $.each(Object.keys(timers), function (i, key) {
            clearTimeout(timers[key]);
            delete timers[key];
        });
        flush();

        return false;
    });
});
