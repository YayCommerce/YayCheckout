/**
 * Keeps a Field's typed placeholder. address-i18n.js writes the country's locale placeholder over it on
 * every country_to_state_changing, the first one on page load: "House number and street name" on
 * Address, for one. render.php prints the typed placeholder on the wrapper.
 */

import { bindOnce } from '../shared/bind-once';
import { onDomReady } from '../shared/dom-ready';
import { jquery } from '../shared/jquery';

const ATTR = 'data-yaycheckout-placeholder';

function restore(): void {
	document.querySelectorAll< HTMLElement >( `[${ ATTR }]` ).forEach( ( wrapper ) => {
		const placeholder = wrapper.getAttribute( ATTR ) ?? '';

		wrapper.querySelectorAll( 'input, select, textarea' ).forEach( ( control ) => {
			// address-i18n.js writes both; select2 and country-select.js read data-placeholder.
			control.setAttribute( 'placeholder', placeholder );
			control.setAttribute( 'data-placeholder', placeholder );
		} );

		wrapper.querySelectorAll( '.select2-selection__placeholder' ).forEach( ( shown ) => {
			shown.textContent = placeholder;
		} );
	} );
}

export function keepPlaceholders(): void {
	bindOnce( 'data-yaycheckout-placeholders', () => {
		onDomReady( restore );

		// On document, so it runs after address-i18n.js's handler on body, whichever script loaded first.
		jquery( document )?.on( 'country_to_state_changing', restore );
	} );
}
