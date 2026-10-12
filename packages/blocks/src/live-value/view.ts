/**
 * Keeps each live value span in step with the checkout: cart values from the JSON element that
 * update_order_review swaps after every recalculation, field values from the form as the shopper types.
 * select2 and WooCommerce's country-select.js fire change through jQuery only, so the listeners are jQuery's.
 * A span without a key is one LiveValues::fill() couldn't fill, and is left as it printed it.
 */

import { onDomReady } from '../shared/dom-ready';
import { fieldText } from '../shared/form-text';
import { jquery } from '../shared/jquery';
import { EMPTY_CLASS, FALLBACK_ATTR, FIELD_PREFIX, KEY_ATTR, LIVE_VALUE_CLASS, VALUES_ID } from './constants';
import './view.css';

interface CartValue {
	text: string;
	empty: boolean;
}

function spans(): HTMLElement[] {
	return [ ...document.querySelectorAll< HTMLElement >( `.${ LIVE_VALUE_CLASS }[${ KEY_ATTR }]` ) ];
}

/** As LiveValues::fill(): the value, or the fallback while the value is empty and the fallback isn't ''. */
function show( span: HTMLElement, text: string, empty: boolean ): void {
	const fallback = span.getAttribute( FALLBACK_ATTR ) ?? '';
	const shown = empty && fallback !== '' ? fallback : text;

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

onDomReady( () => {
	syncCart();
	syncFields();

	// updated_checkout also fires once after load, after address-i18n.js has relabelled and hidden fields.
	jquery( document.body )
		?.on( 'updated_checkout', syncCart )
		.on( 'input change country_to_state_changed updated_checkout', syncFields );
} );
