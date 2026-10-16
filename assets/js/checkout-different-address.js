/**
 * "Use same address for billing", printed by the Different address block when shipping is the main
 * address. While it is ticked, Billing is hidden and its address fields follow Shipping's as the shopper
 * types, so update_order_review taxes on the shipping address. CheckoutDifferentAddress copies the
 * same keys again on submit, so the order is right even if this script never ran.
 *
 * window.yayCheckoutSameBilling comes from BlockRegistry: CheckoutDifferentAddress::SAME_BILLING_FIELD
 * and ::COPIED_KEYS. billing_email is never copied.
 *
 * "Use same address for shipping", printed when billing is main and the block's ticked is "same", holds
 * the opposite of WooCommerce's hidden ship_to_different_address box, so checkout.js does the rest.
 */
jQuery(function ($) {
    $('#yaycheckout-same-shipping').on('change', function () {
        $('#ship-to-different-address-checkbox').prop('checked', !this.checked).trigger('change');
    });

    var data = window.yayCheckoutSameBilling;

    if (!data) {
        return;
    }

    var $box = $('input[type="checkbox"][name="' + data.field + '"]');

    if (!$box.length) {
        return;
    }

    var $billing = $('.yaycheckout-checkout .yaycheckout-billing');
    var shippingSelector = $.map(data.copiedKeys, function (key) {
        return '#shipping_' + key;
    }).join(',');

    // Text fields take the value only: the shipping field's own change already updates the totals,
    // and a change on billing per keystroke would skip checkout.js's typing debounce.
    function copy(key) {
        var $from = $('#shipping_' + key);
        var $to = $('#billing_' + key);

        if (!$from.length || !$to.length || $to.val() === $from.val()) {
            return;
        }

        $to.val($from.val());

        if ($to.is('select')) {
            $to.trigger('change');
        }
    }

    // The billing state list is rebuilt after its country changes, so state waits for country-select.js.
    function copyCountryThenState() {
        if ($('#billing_country').val() === $('#shipping_country').val()) {
            copy('state');
            return;
        }

        $(document.body).one('country_to_state_changed', function () {
            copy('state');
        });
        $('#billing_country').val($('#shipping_country').val()).trigger('change');
    }

    function copyKey(key) {
        if (key === 'country' || key === 'state') {
            copyCountryThenState();
        } else {
            copy(key);
        }
    }

    function sync() {
        var same = $box.is(':checked');

        $billing.toggle(!same);

        if (same) {
            copyCountryThenState();
            $.each(data.copiedKeys, function (i, key) {
                if (key !== 'country' && key !== 'state') {
                    copy(key);
                }
            });
        }
    }

    $box.on('change', sync);

    $(document.body).on('input change', shippingSelector, function () {
        if ($box.is(':checked')) {
            copyKey(this.id.replace(/^shipping_/, ''));
        }
    });

    sync();
});
