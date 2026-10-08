/**
 * Opens a field that render.php printed behind its "+ Add …" link: on a click, when the field gets a
 * value (the login modal fills it through jQuery), or when the country's locale makes it required.
 * WooCommerce's locale script shows and hides the inner row, never our panel, so a locale that hides
 * the row hides the link too. Field and Extra field both load this; a link is bound once.
 */

import { jquery, type Control } from './jquery';

const LINK = '.yaycheckout-reveal-link';
const BOUND = 'yaycheckoutRevealBound';

/** A select always has a value, so only a change counts for it; a hidden input never counts. */
function filled( control: Control ): boolean {
	if ( control instanceof HTMLInputElement ) {
		if ( control.type === 'hidden' ) {
			return false;
		}

		return control.type === 'checkbox' || control.type === 'radio' ? control.checked : control.value !== '';
	}

	return control instanceof HTMLTextAreaElement && control.value !== '';
}

function open( link: HTMLButtonElement, panel: HTMLElement, focus: boolean ): void {
	panel.hidden = false;
	link.hidden = true;
	link.setAttribute( 'aria-expanded', 'true' );

	if ( focus ) {
		panel.querySelector< HTMLElement >( 'input:not([type="hidden"]), select, textarea' )?.focus();
	}
}

function bind( link: HTMLButtonElement ): void {
	const panel = document.getElementById( link.getAttribute( 'aria-controls' ) ?? '' );

	if ( ! panel || link.dataset[ BOUND ] ) {
		return;
	}

	link.dataset[ BOUND ] = '1';

	const controls = Array.from( panel.querySelectorAll< Control >( 'input, select, textarea' ) );
	const row = panel.querySelector< HTMLElement >( '.form-row' );
	const onChange = ( event: Event ) => {
		const target = event.target;

		if ( ! panel.hidden ) {
			return;
		}

		if ( ( target instanceof HTMLSelectElement && target.value !== '' ) || controls.some( filled ) ) {
			open( link, panel, false );
		}
	};

	link.addEventListener( 'click', () => open( link, panel, true ) );

	controls.forEach( ( control ) => {
		control.addEventListener( 'input', onChange );
		control.addEventListener( 'change', onChange );
		jquery( control )?.on( 'change', onChange );
	} );

	jquery( document.body )?.on( 'country_to_state_changed', () => {
		if ( ! panel.hidden || ! row ) {
			return;
		}

		if ( row.classList.contains( 'validate-required' ) ) {
			open( link, panel, false );
			return;
		}

		link.hidden = row.style.display === 'none';
	} );

	if ( controls.some( filled ) ) {
		open( link, panel, false );
	}
}

export function initRevealLinks(): void {
	const init = () => document.querySelectorAll< HTMLButtonElement >( LINK ).forEach( bind );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}
