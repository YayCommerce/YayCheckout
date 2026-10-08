/**
 * Re-checks Groups that hide when their watched blocks are empty, after each update_order_review.
 * PHP owns what "empty" means: HideWhenEmpty marks wrappers and CheckoutFragments marks the
 * fragments it swaps; this script only reads the markers (HideWhenEmpty::BLOCK_ID_ATTR,
 * EMPTY_ATTR, LIVE_ATTR, GROUP_ATTR and HIDDEN_CLASS).
 */
jQuery(function ($) {
    var BLOCK_ID_ATTR = 'data-yaycheckout-block-id';
    var EMPTY_ATTR = 'data-yaycheckout-empty';
    var LIVE_ATTR = 'data-yaycheckout-live';
    var GROUP_ATTR = 'data-yaycheckout-hide-when-empty';
    var HIDDEN_CLASS = 'yaycheckout-hidden-when-empty';

    // A swapped fragment carries the fresh marker; its block wrapper still holds the first paint's.
    function syncLiveMarkers() {
        $('[' + LIVE_ATTR + ']').each(function () {
            var $wrapper = $(this).closest('[' + BLOCK_ID_ATTR + ']');

            if ($(this).is('[' + EMPTY_ATTR + ']')) {
                $wrapper.attr(EMPTY_ATTR, '1');
            } else {
                $wrapper.removeAttr(EMPTY_ATTR);
            }
        });
    }

    /** Mirrors HideWhenEmpty::hidden() in PHP. */
    function hidden($group, ids) {
        return ids.every(function (id) {
            return $group.find('[' + BLOCK_ID_ATTR + '="' + id + '"]').not('[' + EMPTY_ATTR + ']').length === 0;
        });
    }

    $(document.body).on('updated_checkout', function () {
        syncLiveMarkers();

        $('[' + GROUP_ATTR + ']').each(function () {
            var $group = $(this);
            var ids = String($group.attr(GROUP_ATTR)).split(' ').filter(Boolean);

            $group.toggleClass(HIDDEN_CLASS, ids.length > 0 && hidden($group, ids));
        });
    });
});
