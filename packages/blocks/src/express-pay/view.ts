/**
 * Keeps each Express Pay block's empty marker in step with its buttons. Gateways print hidden containers and
 * fill them after load (Stripe shows its own once a wallet is ready), so render.php marks the block empty and
 * this script clears the marker while a button shows. The visibility script then re-checks, so a layout's
 * "OR" divider, hidden while the block is empty, appears with the buttons.
 */

import { onDomReady } from '../shared/dom-ready';
import { jquery } from '../shared/jquery';
import { EMPTY_ATTR, REFRESH_EVENT } from '../shared/visibility-rules';

/** What a gateway's button is made of; hidden inputs (Stripe's order attribution) are not a button. */
const BUTTON_SELECTOR = 'iframe, button, a, img, input:not([type="hidden"])';

/** Whether no element between el and the wrapper is display: none. The wrapper is hidden while empty, so it is not asked. */
function shownWithin( el: Element, wrapper: Element ): boolean {
	for ( let node: Element | null = el; node && node !== wrapper; node = node.parentElement ) {
		if ( getComputedStyle( node ).display === 'none' ) {
			return false;
		}
	}

	return true;
}

function sync( wrapper: Element ): void {
	const empty = ! Array.from( wrapper.querySelectorAll( BUTTON_SELECTOR ) ).some( ( el ) => shownWithin( el, wrapper ) );

	if ( empty !== wrapper.hasAttribute( EMPTY_ATTR ) ) {
		wrapper.toggleAttribute( EMPTY_ATTR, empty );
		jquery( document.body )?.trigger( REFRESH_EVENT );
	}
}

onDomReady( () => {
	document.querySelectorAll( '.yaycheckout-express-pay' ).forEach( ( wrapper ) => {
		sync( wrapper );
		new MutationObserver( () => sync( wrapper ) ).observe( wrapper, {
			subtree: true,
			childList: true,
			attributes: true,
			attributeFilter: [ 'style', 'class', 'hidden' ],
		} );
	} );
} );
