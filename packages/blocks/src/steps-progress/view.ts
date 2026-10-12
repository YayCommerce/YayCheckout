/**
 * Storefront paint for yaycheckout/steps-progress.
 *
 * Steps owns the open step. This script only mirrors data-state.
 */

import { CHECKOUT_SELECTOR, checkoutFor } from '../shared/checkout-root';
import { onDomReady } from '../shared/dom-ready';

const ITEM = '.yaycheckout-steps-progress__item';

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-steps-progress-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-steps-progress-bound', '' );
	document.addEventListener( 'yaycheckout:step-change', onStepChange );
	document.addEventListener( 'click', onClick );
}

function onStepChange( event: Event ): void {
	if ( ! ( event.target instanceof Element ) ) {
		return;
	}

	const checkout = event.target.closest( CHECKOUT_SELECTOR );

	if ( checkout ) {
		sync( checkout );
	}
}

function onClick( event: MouseEvent ): void {
	const target = event.target;

	if ( ! ( target instanceof Element ) ) {
		return;
	}

	const button = target.closest< HTMLButtonElement >( 'button.yaycheckout-steps-progress__jump' );

	if ( ! button || button.disabled ) {
		return;
	}

	const item = button.closest< HTMLElement >( ITEM );
	const checkout = checkoutFor( button );

	if ( ! item || ! checkout || ! item.dataset.step ) {
		return;
	}

	event.preventDefault();
	checkout.dispatchEvent(
		new CustomEvent( 'yaycheckout:step-reopen', {
			bubbles: true,
			detail: { step: item.dataset.step },
		} )
	);
}

function sync( checkout: Element ): void {
	const steps = new Map< string, HTMLElement >();

	checkout.querySelectorAll< HTMLElement >( '.yaycheckout-step' ).forEach( ( step ) => {
		if ( step.dataset.step && step.dataset.state && ! steps.has( step.dataset.step ) ) {
			steps.set( step.dataset.step, step );
		}
	} );

	// Steps Progress may sit outside Checkout, so paint every one on the page.
	document.querySelectorAll< HTMLElement >( ITEM ).forEach( ( item ) => {
		paint( item, steps.get( item.dataset.step ?? '' ) );
	} );

	document.querySelectorAll< HTMLElement >( '.yaycheckout-steps-progress' ).forEach( paintSummary );
}

// The bar style's fill and "Step N of M". Other styles have no summary.
function paintSummary( root: HTMLElement ): void {
	const current = root.querySelector< HTMLElement >( `${ ITEM }[data-state="current"]` );
	const label = root.querySelector( '.yaycheckout-steps-progress__current' );
	const total = root.querySelectorAll( ITEM ).length;
	const step = Number( current?.dataset.step );

	if ( ! label || ! current || ! step || ! total ) {
		return;
	}

	label.textContent = String( step );
	root.style.setProperty( '--yaycheckout-steps-progress-value', String( step / total ) );
}

function paint( item: HTMLElement, step: HTMLElement | undefined ): void {
	const state = step?.dataset.state ?? 'locked';
	item.dataset.state = state;

	// A span when the merchant turned clicking off.
	const button = item.querySelector< HTMLButtonElement >( 'button.yaycheckout-steps-progress__jump' );

	if ( button ) {
		// Mirrors steps/view.ts onClick(): a step the shopper reached reopens unless it is open.
		button.disabled = step?.dataset.reached !== 'true' || state === 'current';
	}

	if ( state === 'current' ) {
		item.setAttribute( 'aria-current', 'step' );
	} else {
		item.removeAttribute( 'aria-current' );
	}
}

onDomReady( boot );
