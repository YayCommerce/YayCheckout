/**
 * What a shopper sees in a checkout form control, read as text: Steps' summary and Field Value
 * both print it, so they read it the same way.
 */

import type { Control } from './jquery';

/** A label's text without WooCommerce's required * and "(optional)" marks, whitespace collapsed. */
export function labelText( label: Element ): string {
	const copy = label.cloneNode( true ) as HTMLElement;
	copy.querySelectorAll( '.required, .optional, abbr, input' ).forEach( ( mark ) => mark.remove() );

	return ( copy.textContent ?? '' ).replace( /\s+/g, ' ' ).trim();
}

/** A select's chosen option text, a radio or checkbox's option label, or the typed value. */
export function controlText( control: Control ): string {
	if ( control instanceof HTMLSelectElement ) {
		return control.value.trim() === '' ? '' : control.selectedOptions[ 0 ]?.text.trim() ?? '';
	}

	if ( control instanceof HTMLInputElement && ( control.type === 'radio' || control.type === 'checkbox' ) ) {
		const label = optionLabel( control );

		return label ? labelText( label ) : control.value.trim();
	}

	return control.value.trim();
}

function optionLabel( control: HTMLInputElement ): Element | null {
	const wrapping = control.closest( 'label' );

	if ( wrapping ) {
		return wrapping;
	}

	const labels = [ ...( control.labels ?? [] ) ];

	return labels.find( ( label ) => label.classList.contains( 'radio' ) || label.classList.contains( 'checkbox' ) ) ?? labels[ 0 ] ?? null;
}
