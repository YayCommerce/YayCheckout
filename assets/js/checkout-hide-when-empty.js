/**
 * Re-checks containers (Group, Columns, Column) that hide when their watched blocks are empty,
 * after each update_order_review and each country change. PHP owns what "empty" means: HideWhenEmpty marks wrappers and
 * CheckoutFragments marks the fragments it swaps; for a Field, WooCommerce's address-i18n.js
 * hides the row for the country. This script only reads the markers (HideWhenEmpty::BLOCK_ID_ATTR,
 * EMPTY_ATTR, LIVE_ATTR, LOCALE_ATTR, CONTAINER_ATTR, HIDDEN_CLASS and REFRESH_EVENT). Field Value marks
 * itself as the shopper types and fires REFRESH_EVENT.
 */
jQuery(function ($) {
    var BLOCK_ID_ATTR = 'data-yaycheckout-block-id';
    var EMPTY_ATTR = 'data-yaycheckout-empty';
    var LIVE_ATTR = 'data-yaycheckout-live';
    var LOCALE_ATTR = 'data-yaycheckout-locale';
    var CONTAINER_ATTR = 'data-yaycheckout-hide-when-empty';
    var HIDDEN_CLASS = 'yaycheckout-hidden-when-empty';
    var REFRESH_EVENT = 'yaycheckout_hide_when_empty_refresh';

    function mark($wrapper, isEmpty) {
        if (isEmpty) {
            $wrapper.attr(EMPTY_ATTR, '1');
        } else {
            $wrapper.removeAttr(EMPTY_ATTR);
        }
    }

    // A swapped fragment carries the fresh marker; its block wrapper still holds the first paint's.
    function syncLiveMarkers() {
        $('[' + LIVE_ATTR + ']').each(function () {
            mark($(this).closest('[' + BLOCK_ID_ATTR + ']'), $(this).is('[' + EMPTY_ATTR + ']'));
        });
    }

    // address-i18n.js hides a Field's row with .hide() when the country's locale hides the field.
    // The row's own display is read, not :hidden, which a hidden container would make true for every row.
    function syncLocaleMarkers() {
        $('[' + LOCALE_ATTR + '][' + BLOCK_ID_ATTR + ']').each(function () {
            var $row = $(this).find('.form-row').addBack('.form-row').filter('[id$="_field"]').first();

            if ($row.length) {
                mark($(this), $row.css('display') === 'none');
            }
        });
    }

    /** Mirrors HideWhenEmpty::hidden() in PHP. */
    function hidden($container, ids) {
        return ids.every(function (id) {
            return $container.find('[' + BLOCK_ID_ATTR + '="' + id + '"]').not('[' + EMPTY_ATTR + ']').length === 0;
        });
    }

    function refresh() {
        syncLiveMarkers();
        syncLocaleMarkers();

        $('[' + CONTAINER_ATTR + ']').each(function () {
            var $container = $(this);
            var ids = String($container.attr(CONTAINER_ATTR)).split(' ').filter(Boolean);

            $container.toggleClass(HIDDEN_CLASS, hidden($container, ids));
        });
    }

    // Bound after address-i18n.js (a dependency), so its show/hide has already run on country_to_state_changing.
    $(document.body).on('updated_checkout country_to_state_changing ' + REFRESH_EVENT, refresh);
});
