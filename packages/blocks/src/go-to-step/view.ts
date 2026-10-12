/**
 * Storefront enable/jump for yaycheckout/go-to-step.
 *
 * Steps owns the open step. This script mirrors data-state and asks Steps to reopen.
 */

import { CHECKOUT_SELECTOR, checkoutFor } from '../shared/checkout-root';
import { onDomReady } from '../shared/dom-ready';

const BUTTON = 'button.yaycheckout-go-to-step__button';

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-go-to-step-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-go-to-step-bound', '' );
	document.addEventListener( 'yaycheckout:step-change', onStepChange );
	document.addEventListener( 'click', onClick );
}

function onStepChange( event: Event ): void {
	const checkout = event.target instanceof Element ? event.target.closest( CHECKOUT_SELECTOR ) : null;

	if ( ! checkout ) {
		return;
	}

	// The block may sit outside Checkout, so paint every one on the page. The first step with that number wins, as in Progress.
	document.querySelectorAll< HTMLButtonElement >( BUTTON ).forEach( ( button ) => {
		const step = checkout.querySelector< HTMLElement >( `.yaycheckout-step[data-step="${ CSS.escape( button.dataset.step ?? '' ) }"]` );

		button.disabled = step?.dataset.state !== 'complete';
	} );
}

function onClick( event: MouseEvent ): void {
	const button = event.target instanceof Element ? event.target.closest< HTMLButtonElement >( BUTTON ) : null;
	const checkout = button ? checkoutFor( button ) : null;

	if ( ! button || button.disabled || ! checkout ) {
		return;
	}

	event.preventDefault();
	// steps/view.ts re-checks the open step first and stays on it when that fails.
	checkout.dispatchEvent(
		new CustomEvent( 'yaycheckout:step-reopen', {
			bubbles: true,
			detail: { step: button.dataset.step },
		} )
	);
}

onDomReady( boot );
