/**
 * Keeps each Field Value block in step with the form fields it names, on every keystroke or pick.
 * select2 and WooCommerce's country-select.js fire change through jQuery only, so the listener is jQuery's.
 * Every block re-syncs on each event: a country change also changes what its state field shows, and
 * WooCommerce's address-i18n.js relabels fields for the country, so the label is read from the form too.
 */

import { jquery, type Control } from '../shared/jquery';

const VALUE = '.yaycheckout-field-value__value[data-field-keys]';

/** Mirrors HideWhenEmpty::EMPTY_ATTR and HideWhenEmpty::REFRESH_EVENT in PHP. */
const EMPTY_ATTR = 'data-yaycheckout-empty';
const REFRESH_EVENT = 'yaycheckout_hide_when_empty_refresh';

function control( key: string ): Control | null {
	return document.querySelector( 'form.checkout' )?.querySelector< Control >( `[name="${ key }"]` ) ?? null;
}

function text( field: Control | null ): string {
	// WooCommerce hides the state field for a country without states; a leftover value there means nothing.
	if ( ! field || ( field instanceof HTMLInputElement && field.type === 'hidden' ) ) {
		return '';
	}

	if ( field instanceof HTMLSelectElement ) {
		return field.value !== '' ? ( field.selectedOptions[ 0 ]?.text ?? '' ).trim() : '';
	}

	return field.value.trim();
}

/** The field's label as the form shows it now, without the required or optional mark. */
function formLabel( key: string ): string {
	const label = document.querySelector( `form.checkout label[for="${ key }"]` )?.cloneNode( true ) as HTMLElement | undefined;

	label?.querySelectorAll( '.required, .optional' ).forEach( ( mark ) => mark.remove() );

	return label?.textContent?.replace( /\u00a0/g, ' ' ).trim() ?? '';
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

function sync(): void {
	let changed = false;

	document.querySelectorAll< HTMLElement >( VALUE ).forEach( ( element ) => {
		const keys = strings( element.dataset.fieldKeys );
		const serverLabels = strings( element.dataset.labels );
		const separator = element.dataset.separator ?? ', ';
		// Empty values are skipped, as render.php does, so a half-filled block shows no dangling separator.
		const value = keys
			.map( ( key ) => text( control( key ) ) )
			.filter( ( part ) => part !== '' )
			.join( separator );
		const label = keys.map( ( key, index ) => formLabel( key ) || serverLabels[ index ] || key ).join( separator );
		// render.php sets data-placeholder only for a typed placeholder; otherwise the labels stand in.
		element.textContent = value !== '' ? value : element.dataset.placeholder || label;
		element.classList.toggle( 'is-empty', value === '' );

		// The wrapper's Hide when empty marker, which HideWhenEmpty::wrapper() printed for the first paint.
		const wrapper = element.parentElement;

		if ( wrapper?.hasAttribute( 'data-yaycheckout-block-id' ) && wrapper.hasAttribute( EMPTY_ATTR ) !== ( value === '' ) ) {
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
