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

	if ( ! item || ! checkout || item.dataset.state !== 'complete' || ! item.dataset.step ) {
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
	const steps = [ ...checkout.querySelectorAll< HTMLElement >( '.yaycheckout-step' ) ];

	checkout.querySelectorAll< HTMLElement >( '.yaycheckout-progress' ).forEach( ( progress ) => {
		progress.querySelectorAll< HTMLElement >( ITEM ).forEach( ( item ) => {
			const step = steps.find( ( candidate ) => candidate.dataset.step === item.dataset.step );

			paint( item, step?.dataset.state ?? 'locked' );
		} );
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
