/**
 * Re-applies the layout's required flags and label suffixes after
 * address-i18n.js resets them on country change. Map is
 * window.yayCheckoutRequired, inlined from RequiredIndicator::script_payload().
 */
jQuery(function ($) {
    var map = window.yayCheckoutRequired || {};
    var fallbackOptional =
        typeof wc_address_i18n_params !== 'undefined' && wc_address_i18n_params.i18n_optional_text
            ? wc_address_i18n_params.i18n_optional_text
            : 'optional';

    function applyRequired() {
        $.each(map, function (key, config) {
            var field = $('#' + key + '_field');
            if (!field.length) {
                return;
            }
            if (config.required) {
                field.find('label .optional').remove();
                field.addClass('validate-required');
                var mark = config.requiredMark !== '' ? config.requiredMark : '*';
                var $req = field.find('label .required');
                if (!$req.length) {
                    field.find('label').append('&nbsp;<span class="required" aria-hidden="true"></span>');
                    $req = field.find('label .required');
                }
                $req.text(mark);
            } else {
                field.find('label .required').remove();
                field.removeClass('validate-required woocommerce-invalid woocommerce-invalid-required-field');
                var optional = config.optionalText !== '' ? config.optionalText : fallbackOptional;
                var $opt = field.find('label .optional');
                if (!$opt.length) {
                    field.find('label').append('&nbsp;<span class="optional"></span>');
                    $opt = field.find('label .optional');
                }
                $opt.text('(' + optional + ')');
            }
        });
    }

    $(document.body).on('country_to_state_changing', applyRequired);
    applyRequired();
});
