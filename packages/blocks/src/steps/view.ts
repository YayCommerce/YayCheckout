/**
 * Storefront open/close for yaycheckout/steps.
 *
 * Continue checks only the open step. The rest of the form is still empty
 * and required, so a form-wide checkValidity() would block step 1.
 */

import { bindOnce } from '../shared/bind-once';
import { canReopenStep, CHECKOUT_SELECTOR, checkoutFor, currentStep } from '../shared/checkout-root';
import { onDomReady } from '../shared/dom-ready';
import { controlsText } from '../shared/form-text';
import type { Control } from '../shared/jquery';

const STEP = '.yaycheckout-step';
const CURRENT = 'current';
const LOCKED = 'locked';
const COMPLETE = 'complete';

type StepState = typeof CURRENT | typeof LOCKED | typeof COMPLETE;

function boot(): void {
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

	// Only a completed step reopens, and every completed step is before the open one.
	if ( ! step || ! canReopenStep( step ) || target.closest( '.yaycheckout-step__body' ) ) {
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

	open( next );
	revealStep( next );
}

/** Makes `step` the open one and locks every step after it, so only the steps before it stay complete. */
function open( step: HTMLElement ): void {
	// Current first, so currentStep() and syncBackButtons() find it while the rest lock.
	setState( step, CURRENT );

	for ( let later = siblingStep( step.nextElementSibling ); later; later = siblingStep( later.nextElementSibling ) ) {
		if ( later.dataset.state !== LOCKED ) {
			setState( later, LOCKED );
		}
	}
}

/**
 * Scrolls an opened step to the top of the visible area, but only when its header is off-screen.
 * The step's scroll-margin-top keeps a gap above it. Focus stays where it is.
 */
function revealStep( step: HTMLElement ): void {
	if ( stepIsInView( step ) ) {
		return;
	}

	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	step.scrollIntoView( { block: 'start', behavior: reduced ? 'auto' : 'smooth' } );
}

/** Whether the step's header is fully inside the viewport, less a sticky header's or pinned bar's scroll padding. */
function stepIsInView( step: HTMLElement ): boolean {
	const root = getComputedStyle( document.documentElement );
	const topInset = parseFloat( root.scrollPaddingTop ) || 0;
	const bottomInset = parseFloat( root.scrollPaddingBottom ) || 0;
	const top = step.getBoundingClientRect().top;
	// With headings hidden the header is a 1px clipped box, so the step's top edge stands in for it.
	const headerHeight = step.querySelector( '.yaycheckout-step__header' )?.getBoundingClientRect().height ?? 0;

	return top >= topInset && top + headerHeight <= window.innerHeight - bottomInset;
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
	// The open step and those after it lock without a check: Continue checks them again.
	if ( ! canReopenStep( step ) ) {
		return;
	}

	open( step );
	// The control that asked may now be disabled or hidden (Steps Progress, Go to Step, Edit), so focus follows the step.
	step.querySelector< HTMLElement >( '.yaycheckout-step__header' )?.focus( { preventScroll: true } );
	revealStep( step );
}

function setState( step: HTMLElement, state: StepState ): void {
	const done = state === COMPLETE;
	step.dataset.state = state;

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

	const checkout = checkoutFor( button );

	return checkout ? currentStep( checkout ) : null;
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

	// Back leaves the open step without checking it; it and every step after it lock. Continue is what checks.
	open( previous );
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

onDomReady( () => bindOnce( 'data-yaycheckout-steps-bound', boot ) );
