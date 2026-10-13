/**
 * Keeps each live value span in step with the checkout: cart values from the JSON element that
 * update_order_review swaps after every recalculation, field values from the form as the shopper types.
 * select2 and WooCommerce's country-select.js fire change through jQuery only, so the listeners are jQuery's.
 * A span without a key is one LiveValues::fill() couldn't fill, and is left as it printed it.
 */

import { onDomReady } from '../shared/dom-ready';
import { fieldText } from '../shared/form-text';
import { jquery } from '../shared/jquery';
import { EMPTY_CLASS, FALLBACK_ATTR, FIELD_PREFIX, KEY_ATTR, LIVE_VALUE_CLASS, PREFIX_ATTR, SUFFIX_ATTR, VALUES_ID } from './constants';
import './view.css';

interface CartValue {
	text: string;
	empty: boolean;
}

function spans(): HTMLElement[] {
	return [ ...document.querySelectorAll< HTMLElement >( `.${ LIVE_VALUE_CLASS }[${ KEY_ATTR }]` ) ];
}

/** As LiveValues::fill_span(): the value between the prefix and suffix, or the fallback alone while the value is empty. */
function show( span: HTMLElement, text: string, empty: boolean ): void {
	const shown = empty
		? span.getAttribute( FALLBACK_ATTR ) ?? ''
		: ( span.getAttribute( PREFIX_ATTR ) ?? '' ) + text + ( span.getAttribute( SUFFIX_ATTR ) ?? '' );

	if ( span.textContent !== shown ) {
		span.textContent = shown;
	}

	span.classList.toggle( EMPTY_CLASS, empty );
}

/** The cart's values from the footer's JSON element; null when it is missing or unreadable. */
function cartValues(): Record< string, CartValue > | null {
	try {
		const parsed: unknown = JSON.parse( document.getElementById( VALUES_ID )?.textContent ?? '' );

		return parsed !== null && typeof parsed === 'object' ? ( parsed as Record< string, CartValue > ) : null;
	} catch {
		return null;
	}
}

function syncCart(): void {
	const values = cartValues();

	// A fragment that didn't parse leaves the spans as they are.
	if ( ! values ) {
		return;
	}

	spans().forEach( ( span ) => {
		const value = values[ span.getAttribute( KEY_ATTR ) ?? '' ];

		if ( value && typeof value.text === 'string' ) {
			show( span, value.text, value.empty === true );
		}
	} );
}

function syncFields(): void {
	const form = document.querySelector( 'form.checkout' );

	spans().forEach( ( span ) => {
		const key = span.getAttribute( KEY_ATTR ) ?? '';

		if ( key.startsWith( FIELD_PREFIX ) ) {
			const text = fieldText( form, key.slice( FIELD_PREFIX.length ) );

			show( span, text, text === '' );
		}
	} );
}

/**
 * WooCommerce's payment_method_selected sets #place_order's text to its data-value on every payment method
 * click, which init_payment_methods() makes after each updated_checkout, and that drops the label's spans.
 * They go back while the text is that label, not a gateway's order_button_text. The observer runs before paint.
 */
function keepPlaceOrderSpans(): void {
	const button = document.getElementById( 'place_order' );

	if ( ! button?.querySelector( `.${ LIVE_VALUE_CLASS }` ) ) {
		return;
	}

	const label = button.innerHTML;

	new MutationObserver( () => {
		if ( ! button.querySelector( `.${ LIVE_VALUE_CLASS }` ) && button.textContent === button.dataset.value ) {
			button.innerHTML = label;
			syncCart();
			syncFields();
		}
	} ).observe( button, { childList: true } );
}

onDomReady( () => {
	syncCart();
	syncFields();
	keepPlaceOrderSpans();

	// updated_checkout also fires once after load, after address-i18n.js has relabelled and hidden fields.
	jquery( document.body )
		?.on( 'updated_checkout', syncCart )
		.on( 'input change country_to_state_changed updated_checkout', syncFields );
} );
