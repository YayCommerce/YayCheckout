/**
 * Applies and removes coupons from the yaycheckout/coupon-form block and prints its notices below it; an applied
 * coupon shows as a chip (CheckoutFragments::coupon_chips_html()). The chips change as soon as WooCommerce answers,
 * before update_checkout refreshes them with the totals.
 * Why it does not reuse WooCommerce's handlers: docs/architecture.md, Storefront (Coupon form).
 */
jQuery(function ($) {
    var ERROR_ID = 'yaycheckout-coupon-error';
    var CHIPS = '.yaycheckout-coupon-form__chips';
    var ERROR_ICON =
        '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm0 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm-.75-7v5.5h1.5V8h-1.5Z"/></svg>';

    function clearError($block) {
        $block
            .find('.yaycheckout-coupon-form__code')
            .removeClass('has-error')
            .removeAttr('aria-invalid aria-describedby');
        $block.find('.yaycheckout-coupon-form__error').remove();
    }

    function showError($block, message) {
        var $notices = $block.find('.yaycheckout-coupon-form__notices');

        $block
            .find('.yaycheckout-coupon-form__code')
            .addClass('has-error')
            .attr({ 'aria-invalid': 'true', 'aria-describedby': ERROR_ID })
            .trigger('focus');

        $('<p>', { class: 'yaycheckout-coupon-form__error', id: ERROR_ID, role: 'alert' })
            .append(ERROR_ICON, $('<span>', { text: message || $notices.data('errorText') }))
            .appendTo($notices);
    }

    function isError(response) {
        return response.indexOf('woocommerce-error') !== -1 || response.indexOf('is-error') !== -1;
    }

    function apply($block) {
        var $button = $block.find('.yaycheckout-coupon-form__apply');
        var $input = $block.find('.yaycheckout-coupon-form__code');
        var $notices = $block.find('.yaycheckout-coupon-form__notices');
        var code = $input.val();

        if ($button.prop('disabled')) {
            return;
        }

        $button.prop('disabled', true);
        clearError($block);
        $notices.empty();

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'),
            data: {
                security: wc_checkout_params.apply_coupon_nonce,
                coupon_code: code,
                // CheckoutFragments::COUPON_FORM_FIELD: drops Woo's success notice for this request only.
                yaycheckout_coupon_form: 1,
                // The block may sit outside the checkout form, so look the email up page-wide.
                billing_email: $('form.checkout input[name="billing_email"]').val(),
            },
            dataType: 'html',
        })
            .done(function (response) {
                var $response = $('<div>').append($.parseHTML(response));
                // CheckoutFragments::print_applied_chips() leads a successful response with the fresh chips.
                var $chips = $response.find(CHIPS).detach();

                if ($chips.length) {
                    $(CHIPS).each(function () {
                        $(this).replaceWith($chips.clone());
                    });
                }

                if (!isError($response.html())) {
                    // Woo's own success notice is dropped server-side (CheckoutFragments::drop_applied_message());
                    // the chip shows the coupon, and notices other extensions add still print here.
                    $notices.html($response.contents());
                    $input.val('');
                } else {
                    showError($block, $response.text().trim());
                }

                $(document.body)
                    .trigger('applied_coupon_in_checkout', [code])
                    .trigger('update_checkout', { update_shipping_method: false });
            })
            .fail(function () {
                showError($block);
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    }

    function remove($chip) {
        var $block = $chip.closest('.yaycheckout-coupon-form');
        var code = $chip.find('.yaycheckout-coupon-form__chip-remove').data('coupon');

        if ($chip.attr('aria-busy') === 'true') {
            return;
        }

        $chip.attr('aria-busy', 'true');
        clearError($block);
        $block.find('.yaycheckout-coupon-form__notices').empty();

        function fail(message) {
            $chip.removeAttr('aria-busy');
            showError($block, message || $block.find('.yaycheckout-coupon-form__notices').data('removeErrorText'));
            // A request can fail after the server removed the coupon (a timeout): the refresh redraws the chips and
            // totals from the cart, and a refresh that fails too leaves the chip.
            $(document.body)
                .one('updated_checkout', function () {
                    // The coupon is gone after all, so the error no longer holds.
                    if (!$block.find('.yaycheckout-coupon-form__chip-remove').filter(function () {
                        return $(this).data('coupon') === code;
                    }).length) {
                        clearError($block);
                    }
                })
                .trigger('update_checkout', { update_shipping_method: false });
        }

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_coupon'),
            data: {
                security: wc_checkout_params.remove_coupon_nonce,
                coupon: code,
            },
            dataType: 'html',
        })
            .done(function (response) {
                if (isError(response)) {
                    fail($($.parseHTML(response)).text().trim());
                    return;
                }

                // Removed, not hidden, so the list collapses (:empty) once the last chip goes; focus leaves with the ×.
                $chip.remove();
                $block.find('.yaycheckout-coupon-form__code').trigger('focus');

                // Woo's "Coupon has been removed." stays unprinted: the chip leaving is the feedback.
                $(document.body)
                    .trigger('removed_coupon_in_checkout', [code])
                    .trigger('update_checkout', { update_shipping_method: false });
            })
            .fail(function () {
                fail();
            });
    }

    $('.yaycheckout-coupon-form')
        .on('click', '.yaycheckout-coupon-form__chip-remove', function (event) {
            event.preventDefault();
            remove($(this).closest('.yaycheckout-coupon-form__chip'));
        })
        .on('keydown', '.yaycheckout-coupon-form__chip-remove', function (event) {
            // A role="button" link: Enter already clicks it, Space must too.
            if (event.key === ' ') {
                event.preventDefault();
                $(this).trigger('click');
            }
        })
        .on('click', '.yaycheckout-coupon-form__apply', function () {
            apply($(this).closest('.yaycheckout-coupon-form'));
        })
        .on('keydown', '.yaycheckout-coupon-form__code', function (event) {
            // Inside the checkout form, Enter would otherwise submit it and place the order.
            if (event.key === 'Enter' && !event.originalEvent.isComposing) {
                event.preventDefault();
                apply($(this).closest('.yaycheckout-coupon-form'));
            }
        })
        .on('input', '.yaycheckout-coupon-form__code', function () {
            clearError($(this).closest('.yaycheckout-coupon-form'));
        });

    // A notice about the coupons in the cart is stale once one is removed, here or from the order review.
    $(document.body).on('removed_coupon_in_checkout', function () {
        $('.yaycheckout-coupon-form').each(function () {
            clearError($(this));
            $(this).find('.yaycheckout-coupon-form__notices').empty();
        });
    });
});
