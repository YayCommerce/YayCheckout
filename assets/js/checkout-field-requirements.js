/**
 * Re-applies the layout's required flags and label suffixes after
 * address-i18n.js resets them on country change. Map is
 * window.yayCheckoutRequired, inlined from RequiredIndicator::script_payload().
 * Repeats RequiredIndicator::span()'s fallback * mark and span markup (owner:
 * RequiredIndicator::span(); change them together). The optional text comes
 * filled in from RequiredIndicator::optional_text().
 */
jQuery(function ($) {
    var map = window.yayCheckoutRequired || {};

    function applyRequired() {
        $.each(map, function (key, config) {
            var field = $('#' + key + '_field');
            // address-i18n.js hides a field the country's locale doesn't use; requiring it would block the order.
            // Its own inline style, not :hidden: rows inside a collapsed div.shipping_address still need their marks.
            if (!field.length || field[0].style.display === 'none') {
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
                var $opt = field.find('label .optional');
                if (!$opt.length) {
                    field.find('label').append('&nbsp;<span class="optional"></span>');
                    $opt = field.find('label .optional');
                }
                $opt.text('(' + config.optionalText + ')');
            }
        });
    }

    $(document.body).on('country_to_state_changing', applyRequired);
    applyRequired();
});
