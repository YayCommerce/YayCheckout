/**
 * Marks a field row, or the Coupon form's field, is-filled while its input holds a value, so
 * checkout-base.css keeps a floating label at the top of the box after the field loses focus.
 * Re-run after WooCommerce swaps or refills fields: a country change rebuilds the state field,
 * address-i18n.js relabels rows, and an applied coupon clears the coupon field.
 */
jQuery(function ($) {
    var form = document.querySelector('.yaycheckout-checkout--floating-labels');
    var controls = '.input-text, textarea, .yaycheckout-coupon-form__code';

    if (!form) {
        return;
    }

    function mark(input) {
        var row = input.closest('.form-row, .yaycheckout-coupon-form__field');

        if (row) {
            row.classList.toggle('is-filled', input.value !== '');
        }
    }

    function markAll() {
        form.querySelectorAll(controls).forEach(mark);
    }

    form.addEventListener('input', function (event) {
        if (event.target.matches(controls)) {
            mark(event.target);
        }
    });
    form.addEventListener('change', function (event) {
        if (event.target.matches(controls)) {
            mark(event.target);
        }
    });

    $(document.body).on('updated_checkout country_to_state_changed', markAll);
    markAll();
});
