/**
 * Storefront paint for yaycheckout/progress.
 *
 * Steps owns the open step. This script only mirrors data-state.
 */

const ITEM = '.yaycheckout-progress__item';
const CHECKOUT = '.yaycheckout-checkout';

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

	const checkout = event.target.closest( CHECKOUT );

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
	const checkout = button.closest( CHECKOUT );

	// paint() keeps the button disabled unless its step is complete.
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
	const states = new Map< string, string >();

	checkout.querySelectorAll< HTMLElement >( '.yaycheckout-step' ).forEach( ( step ) => {
		if ( step.dataset.step && step.dataset.state && ! states.has( step.dataset.step ) ) {
			states.set( step.dataset.step, step.dataset.state );
		}
	} );

	checkout.querySelectorAll< HTMLElement >( ITEM ).forEach( ( item ) => {
		paint( item, states.get( item.dataset.step ?? '' ) ?? 'locked' );
	} );
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

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
