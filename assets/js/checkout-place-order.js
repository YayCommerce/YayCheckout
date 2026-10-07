/**
 * Keeps the Place order button's data-total and aria-label in sync after
 * update_order_review and payment method changes. The amount lives in CSS
 * ::after so WooCommerce's .text() on #place_order does not wipe it.
 */
jQuery(function ($) {
	var BUTTON = '#place_order[data-show-total="1"]';
	var TOTAL_SELECTOR =
		'.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount';

	function button() {
		return $(BUTTON);
	}

	function plainTotal($from) {
		var $amount = $from.find(TOTAL_SELECTOR).first();

		return $amount.length ? $.trim($amount.text()) : '';
	}

	function setAriaLabel($button) {
		var total = $button.attr('data-total') || '';
		var label = $.trim($button.text());

		if (!label || !total) {
			$button.removeAttr('aria-label');
			return;
		}

		$button.attr('aria-label', label + ' · ' + total);
	}

	function applyTotal(total) {
		var $button = button();

		if (!$button.length || !total) {
			return;
		}

		$button.attr('data-total', total);
		setAriaLabel($button);
	}

	$(document.body).on('updated_checkout', function (event, data) {
		var html =
			data &&
			data.fragments &&
			data.fragments['.woocommerce-checkout-review-order-table'];

		if (!html) {
			return;
		}

		applyTotal(plainTotal($('<div>').append($.parseHTML(html))));
	});

	$(document.body).on('payment_method_selected', function () {
		var $button = button();

		if ($button.length) {
			setAriaLabel($button);
		}
	});
});
