/**
 * Re-applies layout required flags after address-i18n.js restores locale defaults
 * on country change. Map is window.yayCheckoutRequired, inlined by
 * Renderer\CheckoutFieldRequirements.
 */
jQuery(function ($) {
    var required = window.yayCheckoutRequired || {};
    var optionalText =
        typeof wc_address_i18n_params !== 'undefined' && wc_address_i18n_params.i18n_optional_text
            ? wc_address_i18n_params.i18n_optional_text
            : 'optional';

    function applyRequired() {
        $.each(required, function (key, isRequired) {
            var field = $('#' + key + '_field');
            if (!field.length) {
                return;
            }
            if (isRequired) {
                field.find('label .optional').remove();
                field.addClass('validate-required');
                if (field.find('label .required[aria-hidden="true"]').length === 0) {
                    field.find('label').append('&nbsp;<span class="required" aria-hidden="true">*</span>');
                }
            } else {
                field.find('label .required').remove();
                field.removeClass('validate-required woocommerce-invalid woocommerce-invalid-required-field');
                if (field.find('label .optional').length === 0) {
                    field.find('label').append('&nbsp;<span class="optional">(' + optionalText + ')</span>');
                }
            }
        });
    }

    $(document.body).on('country_to_state_changing', applyRequired);
    applyRequired();
});
