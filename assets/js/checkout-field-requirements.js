/**
 * Re-applies layout required flags after address-i18n.js restores locale defaults
 * on country change. Map is window.yayCheckoutRequired, inlined by
 * Renderer\CheckoutFieldRequirements.
 */
jQuery(function ($) {
    var map = window.yayCheckoutRequired || {};
    var fallbackOptional =
        typeof wc_address_i18n_params !== 'undefined' && wc_address_i18n_params.i18n_optional_text
            ? wc_address_i18n_params.i18n_optional_text
            : 'optional';

    function configFor(entry) {
        if (entry && typeof entry === 'object') {
            return {
                required: !!entry.required,
                requiredMark: entry.requiredMark || '',
                optionalText: entry.optionalText || '',
            };
        }
        return {
            required: !!entry,
            requiredMark: '',
            optionalText: '',
        };
    }

    function applyRequired() {
        $.each(map, function (key, entry) {
            var field = $('#' + key + '_field');
            if (!field.length) {
                return;
            }
            var config = configFor(entry);
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
