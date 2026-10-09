/**
 * Opens a field that render.php printed behind a Disclosure toggle: on a click, when the field gets
 * a value (the login modal fills it through jQuery), or when the country's locale makes it required.
 * A link opens its field for good and disappears; a collapsible header stays and closes it again,
 * until a locale makes the field required, which opens it and hides the header.
 * WooCommerce's locale script shows and hides the inner row, never our panel, so a locale that hides
 * the row hides the toggle too. Field, Extra field and Coupon form load this; a toggle is bound once.
 */

import { jquery, type Control } from './jquery';

const TOGGLE = '.yaycheckout-disclosure__toggle';
const BOUND = 'yaycheckoutDisclosureBound';

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

function bind( toggle: HTMLButtonElement ): void {
	const panel = document.getElementById( toggle.getAttribute( 'aria-controls' ) ?? '' );

	if ( ! panel || toggle.dataset[ BOUND ] ) {
		return;
	}

	toggle.dataset[ BOUND ] = '1';

	const isLink = toggle.dataset.mode !== 'collapsible';
	const controls = Array.from( panel.querySelectorAll< Control >( 'input, select, textarea' ) );
	const row = panel.querySelector< HTMLElement >( '.form-row' );
	// Once a link is used or a locale requires the field, it stays open with no toggle.
	let settled = false;

	const setOpen = ( open: boolean, focus: boolean ) => {
		panel.hidden = ! open;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		if ( open && isLink ) {
			toggle.hidden = true;
			settled = true;
		}

		if ( open && focus ) {
			panel.querySelector< HTMLElement >( 'input:not([type="hidden"]), select, textarea' )?.focus();
		}
	};

	const onChange = ( event: Event ) => {
		const target = event.target;

		if ( ! panel.hidden ) {
			return;
		}

		if ( ( target instanceof HTMLSelectElement && target.value !== '' ) || controls.some( filled ) ) {
			setOpen( true, false );
		}
	};

	toggle.addEventListener( 'click', () => setOpen( panel.hidden, true ) );

	controls.forEach( ( control ) => {
		control.addEventListener( 'input', onChange );
		control.addEventListener( 'change', onChange );
		jquery( control )?.on( 'change', onChange );
	} );

	jquery( document.body )?.on( 'country_to_state_changed', () => {
		if ( settled || ! row ) {
			return;
		}

		if ( row.classList.contains( 'validate-required' ) ) {
			setOpen( true, false );
			toggle.hidden = true;
			settled = true;
			return;
		}

		toggle.hidden = row.style.display === 'none';
	} );

	if ( controls.some( filled ) ) {
		setOpen( true, false );
	}
}

export function initDisclosures(): void {
	const init = () => document.querySelectorAll< HTMLButtonElement >( TOGGLE ).forEach( bind );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}
