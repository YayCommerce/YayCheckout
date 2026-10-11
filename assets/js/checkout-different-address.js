/**
 * "Use same address for billing", printed by the Different address block when shipping is the main
 * address. While it is ticked, Billing is hidden and its address fields follow Shipping's as the shopper
 * types, so update_order_review taxes on the shipping address. CheckoutDifferentAddress copies the
 * same keys again on submit, so the order is right even if this script never ran.
 */
jQuery(function ($) {
    // Mirrors CheckoutDifferentAddress::SAME_BILLING_FIELD and ::COPIED_KEYS in PHP. billing_email is never copied.
    var SAME_BILLING_FIELD = 'yaycheckout_same_billing';
    var COPIED_KEYS = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'];

    var $box = $('input[type="checkbox"][name="' + SAME_BILLING_FIELD + '"]');

    if (!$box.length) {
        return;
    }

    var $billing = $('.yaycheckout-checkout .yaycheckout-billing');

    function same() {
        return $box.is(':checked');
    }

    function copy(key) {
        var $from = $('#shipping_' + key);
        var $to = $('#billing_' + key);

        if (!$from.length || !$to.length || $to.val() === $from.val()) {
            return;
        }

        $to.val($from.val()).trigger('change');
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
        copy('country');
    }

    function copyAll() {
        copyCountryThenState();

        $.each(COPIED_KEYS, function (i, key) {
            if (key !== 'country' && key !== 'state') {
                copy(key);
            }
        });
    }

    function sync() {
        $billing.toggle(!same());

        if (same()) {
            copyAll();
        }
    }

    $box.on('change', sync);

    $.each(COPIED_KEYS, function (i, key) {
        $(document.body).on('input change', '#shipping_' + key, function () {
            if (!same()) {
                return;
            }

            if (key === 'country' || key === 'state') {
                copyCountryThenState();
            } else {
                copy(key);
            }
        });
    });

    sync();
});
