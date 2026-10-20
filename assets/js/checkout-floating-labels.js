/**
 * Marks a field row, or the Coupon form's field, is-filled while its input holds a value, so
 * checkout-base.css keeps a floating label at the top of the box after the field loses focus.
 * Re-run after WooCommerce swaps or refills fields: a country change rebuilds the state field,
 * address-i18n.js relabels rows, and an applied coupon clears the coupon field.
 */
jQuery(function ($) {
    // Checkout, and a block outside it that takes the layout's label mode (Renderer\CheckoutScope).
    var roots = '.yaycheckout-checkout--floating-labels, .yaycheckout-scope--floating-labels';
    var controls = ':is(' + roots + ') :is(.input-text, textarea, .yaycheckout-coupon-form__code)';

    if (!document.querySelector(roots)) {
        return;
    }

    function mark(input) {
        var row = input.closest('.form-row, .yaycheckout-coupon-form__field');

        if (row) {
            row.classList.toggle('is-filled', input.value !== '');
        }
    }

    function markAll() {
        document.querySelectorAll(controls).forEach(mark);
    }

    function onEdit(event) {
        if (event.target instanceof Element && event.target.matches(controls)) {
            mark(event.target);
        }
    }

    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);

    $(document.body).on('updated_checkout country_to_state_changed', markAll);
    markAll();
});
