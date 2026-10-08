/**
 * Keeps each Field Value block in step with the form field it names, on every keystroke or pick.
 * select2 and WooCommerce's country-select.js fire change through jQuery only, so the listener is jQuery's.
 * Every block re-syncs on each event: a country change also changes what its state field shows, and
 * WooCommerce's address-i18n.js relabels fields for the country, so the label is read from the form too.
 */

import { jquery, type Control } from '../shared/jquery';

const VALUE = '.yaycheckout-field-value__value[data-field-key]';

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

function sync(): void {
	document.querySelectorAll< HTMLElement >( VALUE ).forEach( ( element ) => {
		const key = element.dataset.fieldKey ?? '';
		const value = text( control( key ) );
		const label = formLabel( key );
		const labelElement = element.parentElement?.querySelector( '.yaycheckout-field-value__label' );

		if ( labelElement && label !== '' ) {
			labelElement.textContent = label;
		}

		// render.php sets data-placeholder only for a typed placeholder; otherwise the label stands in.
		const placeholder = element.dataset.placeholder || label || ( labelElement?.textContent ?? '' );

		element.textContent = value !== '' ? value : placeholder;
		element.classList.toggle( 'is-empty', value === '' );
	} );
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
