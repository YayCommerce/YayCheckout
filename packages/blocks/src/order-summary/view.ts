/**
 * Storefront open/close for a collapsible Order Summary: a click anywhere on the header and every Summary
 * Toggle, wherever it sits in the layout, flip the one Summary Details panel and stay in sync. A header
 * without a Toggle is focusable itself (order-summary-header/render.php), so Enter or Space opens it too.
 */

import { bindOnce } from '../shared/bind-once';
import { onDomReady } from '../shared/dom-ready';

// A copy of OrderSummaryCollapse::DETAILS_ID.
const DETAILS_ID = 'yaycheckout-order-summary-details';
// Mirror the markup order-summary/render.php, order-summary-header/render.php and order-summary-toggle/render.php print.
const HEADER = '.is-collapsible .yaycheckout-order-summary-header';
const TOGGLE = '[data-yaycheckout-summary-toggle]';
// A header's own controls keep their action.
const CONTROLS = 'a, button, input, select, textarea, label, [role="button"], [tabindex]';

function boot(): void {
	document.addEventListener( 'click', onClick );
	document.addEventListener( 'keydown', onKeydown );
}

function onClick( event: MouseEvent ): void {
	if ( ! ( event.target instanceof Element ) ) {
		return;
	}

	// A Toggle in the header flips once, as a Toggle; a link or Step button there flips nothing.
	if ( event.target.closest( TOGGLE ) ) {
		flip();
		return;
	}

	const header = event.target.closest( HEADER );
	// A header without a Toggle carries tabindex itself, so it matches CONTROLS too.
	const control = event.target.closest( CONTROLS );

	if ( header && ( ! control || control === header ) ) {
		flip();
	}
}

function onKeydown( event: KeyboardEvent ): void {
	// Neither a role="button" Toggle nor a focusable header turns Enter and Space into a click.
	if ( ( event.key !== 'Enter' && event.key !== ' ' ) || ! ( event.target instanceof Element ) ) {
		return;
	}

	if ( event.target.matches( `${ TOGGLE }, ${ HEADER }` ) ) {
		event.preventDefault();
		flip();
	}
}

function flip(): void {
	const details = document.getElementById( DETAILS_ID );

	if ( ! details ) {
		return;
	}

	const open = details.hidden;

	details.hidden = ! open;
	details.closest( '.yaycheckout-order-summary' )?.classList.toggle( 'is-open', open );

	for ( const control of document.querySelectorAll( TOGGLE ) ) {
		control.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}
}

onDomReady( () => bindOnce( 'data-yaycheckout-summary-bound', boot ) );
