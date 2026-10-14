/**
 * Applies a coupon from the yaycheckout/coupon-form block and prints its notices below it; an applied coupon
 * shows as a chip (CheckoutFragments::coupon_chips_html()).
 * Why it does not reuse WooCommerce's handler: docs/architecture.md, Storefront (Coupon form).
 */
jQuery(function ($) {
    var ERROR_ID = 'yaycheckout-coupon-error';
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
                if (response.indexOf('woocommerce-error') === -1 && response.indexOf('is-error') === -1) {
                    // Woo's own success notice is dropped server-side (CheckoutFragments::drop_applied_message());
                    // the chip shows the coupon, and notices other extensions add still print here.
                    $notices.html(response);
                    $input.val('');
                } else {
                    showError($block, $($.parseHTML(response)).text().trim());
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

    $('.yaycheckout-coupon-form')
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

    // A notice about the coupons in the cart is stale once one is removed from the order review.
    $(document.body).on('removed_coupon_in_checkout', function () {
        $('.yaycheckout-coupon-form').each(function () {
            clearError($(this));
            $(this).find('.yaycheckout-coupon-form__notices').empty();
        });
    });
});
