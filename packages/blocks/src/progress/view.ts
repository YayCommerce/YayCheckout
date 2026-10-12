/**
 * Storefront paint for yaycheckout/progress.
 *
 * Steps owns the open step. This script only mirrors data-state.
 */

import { CHECKOUT_SELECTOR, checkoutFor, reopenStep, stepState } from '../shared/checkout-root';
import { onDomReady } from '../shared/dom-ready';

const ITEM = '.yaycheckout-progress__item';

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-progress-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-progress-bound', '' );
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

	const button = target.closest< HTMLButtonElement >( '.yaycheckout-progress__jump' );

	if ( ! button || button.disabled ) {
		return;
	}

	const item = button.closest< HTMLElement >( ITEM );
	const checkout = checkoutFor( button );

	// paint() keeps the button disabled unless its step is complete.
	if ( ! item || ! checkout || ! item.dataset.step ) {
		return;
	}

	event.preventDefault();
	reopenStep( checkout, item.dataset.step );
}

function sync( checkout: Element ): void {
	// Progress may sit outside Checkout, so paint every one on the page.
	document.querySelectorAll< HTMLElement >( ITEM ).forEach( ( item ) => {
		paint( item, stepState( checkout, item.dataset.step ?? '' ) ?? 'locked' );
	} );

	document.querySelectorAll< HTMLElement >( '.yaycheckout-progress' ).forEach( paintSummary );
}

// The bar style's fill and "Step N of M". Other styles have no summary.
function paintSummary( root: HTMLElement ): void {
	const current = root.querySelector< HTMLElement >( `${ ITEM }[data-state="current"]` );
	const label = root.querySelector( '.yaycheckout-progress__current' );
	const total = root.querySelectorAll( ITEM ).length;
	const step = Number( current?.dataset.step );

	if ( ! label || ! current || ! step || ! total ) {
		return;
	}

	label.textContent = String( step );
	root.style.setProperty( '--yaycheckout-progress-value', String( step / total ) );
}

function paint( item: HTMLElement, state: string ): void {
	item.dataset.state = state;

	const button = item.querySelector< HTMLButtonElement >( '.yaycheckout-progress__jump' );

	if ( button ) {
		button.disabled = state !== 'complete';
	}

	if ( state === 'current' ) {
		item.setAttribute( 'aria-current', 'step' );
	} else {
		item.removeAttribute( 'aria-current' );
	}
}

onDomReady( boot );
