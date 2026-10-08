/**
 * Keeps each Field Value block in step with the form field it names, on every keystroke or pick.
 * select2 and WooCommerce's country-select.js fire change through jQuery only, so the listener is jQuery's.
 * Every block re-syncs on each event: a country change also changes what its state field shows.
 */

import { jquery, type Control } from '../shared/jquery';

const VALUE = '.yaycheckout-field-value__value[data-field-key]';

/** Mirrors $yaycheckout_read's shipping fallback in field-value/render.php. */
function control( key: string ): Control | null {
	const form = document.querySelector( 'form.checkout' );
	const find = ( name: string ) => form?.querySelector< Control >( `[name="${ name }"]` ) ?? null;

	// Without a ship_to_different_address checkbox WooCommerce copies billing into shipping.
	return find( key ) ?? ( key.startsWith( 'shipping_' ) ? find( key.replace( /^shipping_/, 'billing_' ) ) : null );
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

function sync(): void {
	document.querySelectorAll< HTMLElement >( VALUE ).forEach( ( element ) => {
		const value = text( control( element.dataset.fieldKey ?? '' ) );

		element.textContent = value !== '' ? value : element.dataset.placeholder ?? '';
		element.classList.toggle( 'is-empty', value === '' );
	} );
}

function init(): void {
	sync();

	jquery( document.body )?.on( 'input change', sync );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
