/**
 * Storefront open/close for yaycheckout/steps.
 *
 * Continue checks only the open step. The rest of the form is still empty
 * and required, so a form-wide checkValidity() would block step 1.
 */

import { CHECKOUT_SELECTOR, checkoutFor } from '../shared/checkout-root';
import { onDomReady } from '../shared/dom-ready';
import { controlsText } from '../shared/form-text';
import type { Control } from '../shared/jquery';

const STEP = '.yaycheckout-step';
const CURRENT = 'current';
const LOCKED = 'locked';
const COMPLETE = 'complete';

type StepState = typeof CURRENT | typeof LOCKED | typeof COMPLETE;

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-steps-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-steps-bound', '' );
	document.addEventListener( 'click', onClick );
	document.addEventListener( 'yaycheckout:step-reopen', onReopen );
	syncBackButtons();
}

function onReopen( event: Event ): void {
	// reopen() stays in this file. A control outside the step sends this event.
	if ( ! ( event instanceof CustomEvent ) || ! ( event.target instanceof Element ) ) {
		return;
	}

	const stepNumber = event.detail?.step;

	if ( typeof stepNumber !== 'string' || stepNumber === '' ) {
		return;
	}

	const checkout = event.target.closest( CHECKOUT_SELECTOR );
	const step = checkout?.querySelector< HTMLElement >( `${ STEP }[data-step="${ CSS.escape( stepNumber ) }"]` );

	if ( step ) {
		reopen( step );
	}
}

function onClick( event: MouseEvent ): void {
	const target = event.target;

	if ( ! ( target instanceof Element ) ) {
		return;
	}

	// The Previous block's cart link shares its class and must navigate, so only its button is matched.
	const button = target.closest< HTMLButtonElement >( '.yaycheckout-step-button, button.yaycheckout-back-button' );

	if ( button ) {
		event.preventDefault();
		const step = stepForButton( button );

		if ( step && step.dataset.state === CURRENT ) {
			( button.classList.contains( 'yaycheckout-step-button' ) ? advance : retreat )( step );
		}
		return;
	}

	const step = target.closest< HTMLElement >( STEP );

	if ( ! step || step.dataset.state !== COMPLETE || target.closest( '.yaycheckout-step__body' ) ) {
		return;
	}

	event.preventDefault();
	reopen( step );
}

function advance( step: HTMLElement ): void {
	const next = siblingStep( step.nextElementSibling );

	// Next always shows. On the last step it does nothing: completing that step would close it over Place order.
	if ( ! next || ! complete( step ) ) {
		return;
	}

	setState( next, CURRENT );
	revealStep( next );
}

/** Scrolls the step Next or Back opened to the top of the viewport. Focus stays where it is. */
function revealStep( step: HTMLElement ): void {
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	step.scrollIntoView( { block: 'start', behavior: reduced ? 'auto' : 'smooth' } );
}

/** Checks the step and, when it passes, marks it complete with its summary. */
function complete( step: HTMLElement ): boolean {
	const controls = visibleControls( step );

	if ( ! stepIsValid( step, controls ) ) {
		return false;
	}

	writeSummary( step, controlsText( controls ) );
	setState( step, COMPLETE );
	return true;
}

function reopen( step: HTMLElement ): void {
	const steps = step.closest( '.yaycheckout-steps' );

	if ( ! steps ) {
		return;
	}

	const blocked = [ ...steps.querySelectorAll< HTMLElement >( STEP ) ].some(
		( other ) => other !== step && other.dataset.state === CURRENT && ! park( other )
	);

	if ( blocked ) {
		return;
	}

	setState( step, CURRENT );
}

function park( step: HTMLElement ): boolean {
	// A reopened step is current too. Locking it would drop a step the shopper already finished.
	if ( step.dataset.completed !== 'true' ) {
		setState( step, LOCKED );
		return true;
	}

	// Values may have changed since Continue. Leaving must re-check them.
	return complete( step );
}

function setState( step: HTMLElement, state: StepState ): void {
	const done = state === COMPLETE;
	step.dataset.state = state;

	if ( done ) {
		step.dataset.completed = 'true';
	}

	const summary = step.querySelector< HTMLElement >( '.yaycheckout-step__summary' );

	if ( summary ) {
		summary.hidden = ! done || summary.textContent === '';
	}

	const edit = step.querySelector< HTMLButtonElement >( '.yaycheckout-step__edit' );

	if ( edit ) {
		edit.hidden = ! done;
	}

	syncBackButtons();
	// A dataset write has no subscribers. The event is how other scripts hear it.
	step.dispatchEvent( new CustomEvent( 'yaycheckout:step-change', { bubbles: true } ) );
}

function stepForButton( button: HTMLElement ): HTMLElement | null {
	const inside = button.closest< HTMLElement >( STEP );

	if ( inside ) {
		return inside;
	}

	return checkoutFor( button )?.querySelector< HTMLElement >( `${ STEP }[data-state="${ CURRENT }"]` ) ?? null;
}

/**
 * On the first step, or with no step, Previous hides its button and shows its cart link
 * (the button's next element) when it has one. A button inside a step follows that step.
 */
function syncBackButtons(): void {
	document.querySelectorAll< HTMLButtonElement >( 'button.yaycheckout-back-button' ).forEach( ( button ) => {
		const step = stepForButton( button );
		const atFirst = ! step || ! siblingStep( step.previousElementSibling );
		const link = button.nextElementSibling;

		button.hidden = atFirst;

		if ( link instanceof HTMLElement && link.matches( '.yaycheckout-back-button--cart' ) ) {
			link.hidden = ! atFirst;
		}
	} );
}

function writeSummary( step: HTMLElement, line: string ): void {
	const summary = step.querySelector< HTMLElement >( '.yaycheckout-step__summary' );

	if ( summary ) {
		summary.textContent = line;
	}
}

function retreat( step: HTMLElement ): void {
	const previous = siblingStep( step.previousElementSibling );

	if ( ! previous ) {
		return;
	}

	// Back leaves the open step without re-checking it. Continue is what checks.
	setState( step, step.dataset.completed === 'true' ? COMPLETE : LOCKED );

	setState( previous, CURRENT );
	revealStep( previous );
}

function siblingStep( sibling: Element | null ): HTMLElement | null {
	return sibling instanceof HTMLElement && sibling.matches( STEP ) ? sibling : null;
}

function stepIsValid( step: HTMLElement, controls: Control[] ): boolean {
	const seenRadios = new Set< string >();

	for ( const control of controls ) {
		if ( control instanceof HTMLInputElement && control.type === 'radio' ) {
			if ( seenRadios.has( control.name ) ) {
				continue;
			}
			seenRadios.add( control.name );
			if ( ! radioGroupIsValid( step, control ) ) {
				return false;
			}
			continue;
		}

		if ( ! controlIsValid( step, control ) ) {
			return false;
		}
	}

	return true;
}

function visibleControls( step: HTMLElement ): Control[] {
	return [ ...step.querySelectorAll< Control >( 'input, select, textarea' ) ].filter( ( control ) => {
		if ( control.disabled || ! isShown( control ) ) {
			return false;
		}

		return ! ( control instanceof HTMLInputElement && [ 'hidden', 'button', 'submit' ].includes( control.type ) );
	} );
}

function controlIsValid( step: HTMLElement, control: Control ): boolean {
	control.setCustomValidity( '' );

	if ( control.value.trim() === '' && isRowRequired( control ) && ! control.required ) {
		control.setCustomValidity( message( step, 'requiredMessage' ) );
	}

	const valid = control.reportValidity();

	if ( ! valid ) {
		const clear = () => control.setCustomValidity( '' );
		control.addEventListener( 'input', clear, { once: true } );
		control.addEventListener( 'change', clear, { once: true } );
	}

	return valid;
}

function radioGroupIsValid( step: HTMLElement, radio: HTMLInputElement ): boolean {
	if ( radio.name === '' ) {
		return true;
	}

	const group = [ ...step.querySelectorAll< HTMLInputElement >( `input[type="radio"][name="${ CSS.escape( radio.name ) }"]` ) ];
	const mustChoose = group.some( ( item ) => item.required || item.getAttribute( 'aria-required' ) === 'true' || isRowRequired( item ) || isChoiceList( item ) );

	if ( ! mustChoose || group.some( ( item ) => item.checked ) ) {
		return true;
	}

	const first = group.find( ( item ) => item.getClientRects().length > 0 ) ?? group[ 0 ];
	first.setCustomValidity( message( step, 'selectMessage' ) );
	const valid = first.reportValidity();
	first.addEventListener( 'change', () => first.setCustomValidity( '' ), { once: true } );

	return valid;
}

function isChoiceList( radio: HTMLInputElement ): boolean {
	return radio.name === 'payment_method' || radio.name.startsWith( 'shipping_method' );
}

function isShown( control: HTMLElement ): boolean {
	// Select2 clips the real <select> off-screen; it is still the control that must validate.
	if ( control.classList.contains( 'select2-hidden-accessible' ) ) {
		return true;
	}

	return control.getClientRects().length > 0;
}

function isRowRequired( control: Element ): boolean {
	return !! control.closest( '.form-row.validate-required' );
}

function message( step: HTMLElement, key: 'requiredMessage' | 'selectMessage' ): string {
	const steps = step.closest< HTMLElement >( '.yaycheckout-steps' );

	return steps?.dataset[ key ] ?? '';
}

onDomReady( boot );
