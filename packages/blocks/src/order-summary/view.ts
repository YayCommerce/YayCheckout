/**
 * Storefront open/close for a collapsible Order Summary: the header's overlay button and every Summary
 * Toggle, wherever it sits in the layout, flip the one Summary Details panel and stay in sync.
 */

import { onDomReady } from '../shared/dom-ready';

// A copy of OrderSummaryCollapse::DETAILS_ID.
const DETAILS_ID = 'yaycheckout-order-summary-details';
// Mirror the markup order-summary-header/render.php and order-summary-toggle/render.php print.
const HEADER_BUTTON = '.yaycheckout-order-summary__header-button';
const TOGGLE = '[data-yaycheckout-summary-toggle]';

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-summary-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-summary-bound', '' );
	document.addEventListener( 'click', onClick );
	document.addEventListener( 'keydown', onKeydown );
}

function onClick( event: MouseEvent ): void {
	// The overlay is the header's sibling layer, not an ancestor of its blocks, so a click on a Toggle in
	// the header reaches only the Toggle, and a link or Step button there never reaches either.
	if ( event.target instanceof Element && event.target.closest( `${ HEADER_BUTTON }, ${ TOGGLE }` ) ) {
		flip();
	}
}

function onKeydown( event: KeyboardEvent ): void {
	// A real button already turns Enter and Space into a click; a role="button" Toggle doesn't.
	if ( ( event.key !== 'Enter' && event.key !== ' ' ) || ! ( event.target instanceof Element ) ) {
		return;
	}

	if ( event.target.matches( TOGGLE ) ) {
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

	for ( const control of document.querySelectorAll( `${ HEADER_BUTTON }, ${ TOGGLE }` ) ) {
		control.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}
}

onDomReady( boot );
