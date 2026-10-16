/**
 * Storefront enable/jump for yaycheckout/go-to-step.
 *
 * Steps owns the open step. This script mirrors data-state and asks Steps to reopen.
 */

import { bindOnce } from '../../shared/bind-once';
import { canReopenStep, CHECKOUT_SELECTOR, checkoutFor, reopenStep } from '../../shared/checkout-root';
import { onDomReady } from '../../shared/dom-ready';

const BUTTON = 'button.yaycheckout-go-to-step__button';

function boot(): void {
	document.addEventListener( 'yaycheckout:step-change', onStepChange );
	document.addEventListener( 'click', onClick );
}

function onStepChange( event: Event ): void {
	const checkout = event.target instanceof Element ? event.target.closest( CHECKOUT_SELECTOR ) : null;

	if ( ! checkout ) {
		return;
	}

	// The block may sit outside Checkout, so paint every one on the page. The first step with that number wins, as in Steps Progress.
	document.querySelectorAll< HTMLButtonElement >( BUTTON ).forEach( ( button ) => {
		const step = checkout.querySelector< HTMLElement >( `.yaycheckout-step[data-step="${ CSS.escape( button.dataset.step ?? '' ) }"]` );

		button.disabled = ! canReopenStep( step );
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
	reopenStep( checkout, button.dataset.step ?? '' );
}

onDomReady( () => bindOnce( 'data-yaycheckout-go-to-step-bound', boot ) );
