/**
 * Keeps each Field Value block in step with the form fields it names, on every keystroke or pick.
 * select2 and WooCommerce's country-select.js fire change through jQuery only, so the listener is jQuery's.
 * Every block re-syncs on each event: a country change also changes what its state field shows, and
 * WooCommerce's address-i18n.js relabels fields for the country, so the label is read from the form too.
 */

import { controlsText, labelText } from '../shared/form-text';
import { jquery, type Control } from '../shared/jquery';

const VALUE = '.yaycheckout-field-value__value[data-field-keys]';

/** Mirrors HideWhenEmpty::BLOCK_ID_ATTR, HideWhenEmpty::EMPTY_ATTR and HideWhenEmpty::REFRESH_EVENT in PHP. */
const BLOCK_ID_ATTR = 'data-yaycheckout-block-id';
const EMPTY_ATTR = 'data-yaycheckout-empty';
const REFRESH_EVENT = 'yaycheckout_hide_when_empty_refresh';

interface Source {
	keys: string[];
	serverLabels: string[];
	separator: string;
}

/** What render.php printed on each value element, parsed once rather than on every keystroke. */
const sources = new WeakMap< HTMLElement, Source >();

function text( form: Element | null, key: string ): string {
	// A radio group, or an Extra field's checkboxes posted as key[], is several controls under one name.
	const controls = [ ...( form?.querySelectorAll< Control >( `[name="${ key }"], [name="${ key }[]"]` ) ?? [] ) ];
	const field = controls[ 0 ];
	const row = form?.querySelector< HTMLElement >( `#${ key }_field` );

	// WooCommerce hides the state field for a country without states, and address-i18n.js hides the row of a
	// field the country's locale hides; a leftover value there means nothing. Mirrors syncLocaleMarkers() in
	// checkout-hide-when-empty.js, which follows CheckoutFieldDefaults::hidden_for_country().
	if ( ! field || ( field instanceof HTMLInputElement && field.type === 'hidden' ) || ( row && getComputedStyle( row ).display === 'none' ) ) {
		return '';
	}

	return controlsText( controls );
}

/** The field's label as the form shows it now, without the required or optional mark. */
function formLabel( key: string ): string {
	const label = document.querySelector( `form.checkout label[for="${ key }"]` );

	return label ? labelText( label ) : '';
}

/** A JSON array of strings from a data attribute render.php printed; anything else reads as empty. */
function strings( json: string | undefined ): string[] {
	try {
		const parsed: unknown = JSON.parse( json ?? '[]' );

		return Array.isArray( parsed ) ? parsed.map( String ) : [];
	} catch {
		return [];
	}
}

function source( element: HTMLElement ): Source {
	let parsed = sources.get( element );

	if ( ! parsed ) {
		parsed = {
			keys: strings( element.dataset.fieldKeys ),
			serverLabels: strings( element.dataset.labels ),
			separator: element.dataset.separator ?? ', ',
		};
		sources.set( element, parsed );
	}

	return parsed;
}

function sync(): void {
	const form = document.querySelector( 'form.checkout' );
	let changed = false;

	document.querySelectorAll< HTMLElement >( VALUE ).forEach( ( element ) => {
		const { keys, serverLabels, separator } = source( element );
		// Empty values are skipped, as render.php does, so a half-filled block shows no dangling separator.
		const value = keys
			.map( ( key ) => text( form, key ) )
			.filter( ( part ) => part !== '' )
			.join( separator );
		// render.php sets data-placeholder only for a typed placeholder; otherwise the labels stand in.
		const shown =
			value ||
			element.dataset.placeholder ||
			keys.map( ( key, index ) => formLabel( key ) || serverLabels[ index ] || key ).join( separator );

		if ( element.textContent !== shown ) {
			element.textContent = shown;
		}

		element.classList.toggle( 'is-empty', value === '' );

		// The wrapper's Hide when empty marker, which HideWhenEmpty::wrapper() printed for the first paint.
		const wrapper = element.parentElement;

		if ( wrapper?.hasAttribute( BLOCK_ID_ATTR ) && wrapper.hasAttribute( EMPTY_ATTR ) !== ( value === '' ) ) {
			wrapper.toggleAttribute( EMPTY_ATTR, value === '' );
			changed = true;
		}
	} );

	// A container watching this block re-checks only on WooCommerce's events, so tell it a value came or went.
	if ( changed ) {
		jquery( document.body )?.trigger( REFRESH_EVENT );
	}
}

function init(): void {
	sync();

	// updated_checkout also fires once after load, after address-i18n.js has relabelled the fields.
	jquery( document.body )?.on( 'input change country_to_state_changed updated_checkout', sync );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
