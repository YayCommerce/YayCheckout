/**
 * What a shopper sees in a checkout form control, read as text: Steps' summary, Field Value and live
 * values all print it, so they read it the same way.
 */

import type { Control } from './jquery';

/** A label's text without WooCommerce's required * and "(optional)" marks, whitespace collapsed. */
export function labelText( label: Element ): string {
	const copy = label.cloneNode( true ) as HTMLElement;
	copy.querySelectorAll( '.required, .optional, abbr, input' ).forEach( ( mark ) => mark.remove() );

	return ( copy.textContent ?? '' ).replace( /\s+/g, ' ' ).trim();
}

/** A select's chosen option text, a radio or checkbox's option label, or the typed value. */
function controlText( control: Control ): string {
	if ( control instanceof HTMLSelectElement ) {
		return control.value.trim() === '' ? '' : control.selectedOptions[ 0 ]?.text.trim() ?? '';
	}

	if ( control instanceof HTMLInputElement && ( control.type === 'radio' || control.type === 'checkbox' ) ) {
		const label = optionLabel( control );

		return label ? labelText( label ) : control.value.trim();
	}

	return control.value.trim();
}

/** Every control's text, skipping unticked radios and checkboxes, as one comma-separated line. */
export function controlsText( controls: Iterable< Control > ): string {
	const values: string[] = [];

	for ( const control of controls ) {
		if ( control instanceof HTMLInputElement && ( control.type === 'radio' || control.type === 'checkbox' ) && ! control.checked ) {
			continue;
		}

		const value = controlText( control );

		if ( value !== '' ) {
			values.push( value );
		}
	}

	return values.join( ', ' );
}

/**
 * The text of the form field named key, as the shopper sees it; '' when the form has no such field or
 * hides it for the country.
 */
export function fieldText( form: Element | null, key: string ): string {
	// A radio group, or an Extra field's checkboxes posted as key[], is several controls under one name.
	const controls = [ ...( form?.querySelectorAll< Control >( `[name="${ key }"], [name="${ key }[]"]` ) ?? [] ) ];
	const field = controls[ 0 ];
	const row = form?.querySelector< HTMLElement >( `#${ key }_field` );

	// WooCommerce hides the state field for a country without states, and address-i18n.js hides the row of a
	// field the country's locale hides; a leftover value there means nothing. Mirrors syncLocaleMarkers() in
	// visibility/view.ts, which follows CheckoutFieldDefaults::hidden_for_country().
	if ( ! field || ( field instanceof HTMLInputElement && field.type === 'hidden' ) || ( row && getComputedStyle( row ).display === 'none' ) ) {
		return '';
	}

	return controlsText( controls );
}

function optionLabel( control: HTMLInputElement ): Element | null {
	const wrapping = control.closest( 'label' );

	if ( wrapping ) {
		return wrapping;
	}

	const labels = [ ...( control.labels ?? [] ) ];

	return labels.find( ( label ) => label.classList.contains( 'radio' ) || label.classList.contains( 'checkbox' ) ) ?? labels[ 0 ] ?? null;
}
