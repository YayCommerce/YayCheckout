/**
 * WooCommerce's loading overlay on the Order Summary. checkout.js blocks only `.woocommerce-checkout-review-order-table`
 * while update_order_review runs, and the summary blocks drop that class so Woo's fragment can't replace them
 * (CheckoutFragments::fragments()). This blocks the summary's wrapper the way checkout.js blocks that table, from the
 * stepper's request or a refresh until the refresh completes. The wrapper outlives the refresh: only the fragment
 * roots inside it are replaced, so the overlay stays put.
 */
jQuery(function ($) {
    var SUMMARY = '.wp-block-yaycheckout-order-summary';
    // A summary without blocks prints Woo's own table, which checkout.js blocks itself.
    var WOO_TABLE = '.woocommerce-checkout-review-order-table';
    var REFRESH = 'update_order_review';
    // CartItemQuantity::AJAX_ACTION. The stepper always fires update_checkout afterwards, which unblocks.
    var QUANTITY = 'yaycheckout_cart_item_quantity';

    function targets() {
        return $(SUMMARY).filter(function () {
            return 0 === $(this).find(WOO_TABLE).length;
        });
    }

    $(document).on('ajaxSend', function (event, xhr, settings) {
        var url = String(settings.url);

        if (-1 === url.indexOf(REFRESH) && -1 === url.indexOf(QUANTITY)) {
            return;
        }

        // checkout.js's own arguments, and its `processing` class, which its is_blocked() reads.
        targets()
            .not('.processing')
            .addClass('processing')
            .block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
    });

    // An aborted refresh is followed by a newer one. A failed one unblocks too, unlike Woo's payment area.
    $(document).on('ajaxComplete', function (event, xhr, settings) {
        if (-1 === String(settings.url).indexOf(REFRESH) || 'abort' === xhr.statusText) {
            return;
        }

        targets().filter('.processing').removeClass('processing').unblock();
    });
});
