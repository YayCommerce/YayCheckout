/**
 * Storefront open/close for yaycheckout/steps.
 *
 * Continue checks only the open step. The rest of the form is still empty
 * and required, so a form-wide checkValidity() would block step 1.
 */

const STEP = '.yaycheckout-step';
const CURRENT = 'current';
const LOCKED = 'locked';
const COMPLETE = 'complete';

type Control = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-steps-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-steps-bound', '' );
	document.addEventListener( 'click', onClick );
	syncStepButtons();
}

function onClick( event: MouseEvent ): void {
	const target = event.target;

	if ( ! ( target instanceof Element ) ) {
		return;
	}

	const stepButton = target.closest< HTMLButtonElement >( '.yaycheckout-step-button' );

	if ( stepButton ) {
		event.preventDefault();
		const step = stepForButton( stepButton );

		if ( step && step.dataset.state === CURRENT ) {
			advance( step );
		}
		return;
	}

	const backButton = target.closest< HTMLButtonElement >( '.yaycheckout-back-button' );

	if ( backButton ) {
		event.preventDefault();
		const step = stepForButton( backButton );

		if ( step && step.dataset.state === CURRENT ) {
			retreat( step );
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

type StepState = typeof CURRENT | typeof LOCKED | typeof COMPLETE;

function advance( step: HTMLElement ): void {
	if ( ! stepIsValid( step ) ) {
		return;
	}

	writeSummary( step, summaryText( step ) );
	setState( step, COMPLETE );
	openNext( step );
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
	if ( ! stepIsValid( step ) ) {
		return false;
	}

	writeSummary( step, summaryText( step ) );
	setState( step, COMPLETE );
	return true;
}

function setState( step: HTMLElement, state: StepState ): void {
	step.dataset.state = state;

	if ( state === COMPLETE ) {
		step.dataset.completed = 'true';
	}

	const done = state === COMPLETE;
	const summary = step.querySelector< HTMLElement >( '.yaycheckout-step__summary' );

	if ( summary ) {
		summary.hidden = ! done || summary.textContent === '';
	}

	const edit = step.querySelector< HTMLButtonElement >( '.yaycheckout-step__edit' );

	if ( edit ) {
		edit.hidden = ! done;
	}

	syncStepButtons();
}

function stepForButton( button: HTMLElement ): HTMLElement | null {
	const inside = button.closest< HTMLElement >( STEP );

	if ( inside ) {
		return inside;
	}

	return button.closest( 'form.checkout' )?.querySelector< HTMLElement >( `${ STEP }[data-state="${ CURRENT }"]` ) ?? null;
}

function canAdvance( step: HTMLElement ): boolean {
	// The Place order block's wrapper is always there. The submit id appears only with a cart.
	if ( step.querySelector( '#place_order, .yaycheckout-place-order' ) ) {
		return false;
	}

	return nextStep( step ) !== null;
}

function syncStepButtons(): void {
	document.querySelectorAll< HTMLButtonElement >( '.yaycheckout-step-button' ).forEach( ( button ) => {
		const step = stepForButton( button );

		button.hidden = ! step || ! canAdvance( step );
	} );
}

function writeSummary( step: HTMLElement, line: string ): void {
	const summary = step.querySelector< HTMLElement >( '.yaycheckout-step__summary' );

	if ( summary ) {
		summary.textContent = line;
	}
}

function openNext( step: HTMLElement ): void {
	const sibling = nextStep( step );

	if ( sibling ) {
		setState( sibling, CURRENT );
	}
}

function retreat( step: HTMLElement ): void {
	const previous = previousStep( step );

	if ( ! previous ) {
		return;
	}

	// Back leaves the open step without re-checking it. Continue is what checks.
	if ( step.dataset.completed === 'true' ) {
		setState( step, COMPLETE );
	} else {
		setState( step, LOCKED );
	}

	setState( previous, CURRENT );
}

function nextStep( step: HTMLElement ): HTMLElement | null {
	const sibling = step.nextElementSibling;

	return sibling instanceof HTMLElement && sibling.matches( STEP ) ? sibling : null;
}

function previousStep( step: HTMLElement ): HTMLElement | null {
	const sibling = step.previousElementSibling;

	return sibling instanceof HTMLElement && sibling.matches( STEP ) ? sibling : null;
}

function stepIsValid( step: HTMLElement ): boolean {
	const seenRadios = new Set< string >();

	for ( const control of visibleControls( step ) ) {
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
	const row = control.closest( '.form-row' );

	return !! row && row.classList.contains( 'validate-required' );
}

function message( step: HTMLElement, key: 'requiredMessage' | 'selectMessage' ): string {
	const steps = step.closest< HTMLElement >( '.yaycheckout-steps' );

	return steps?.dataset[ key ] ?? '';
}

function summaryText( step: HTMLElement ): string {
	const values: string[] = [];

	for ( const control of visibleControls( step ) ) {
		if ( control instanceof HTMLInputElement && ( control.type === 'radio' || control.type === 'checkbox' ) && ! control.checked ) {
			continue;
		}

		const value = textOf( control );

		if ( value !== '' ) {
			values.push( value );
		}
	}

	return values.join( ', ' );
}

function textOf( control: Control ): string {
	if ( control instanceof HTMLSelectElement ) {
		return control.value.trim() === '' ? '' : control.selectedOptions[ 0 ]?.text.trim() ?? '';
	}

	if ( control instanceof HTMLInputElement && ( control.type === 'radio' || control.type === 'checkbox' ) ) {
		return labelFor( control );
	}

	return control.value.trim();
}

function labelFor( control: HTMLInputElement ): string {
	const label = optionLabel( control );

	if ( ! label ) {
		return control.value.trim();
	}

	// Woo puts the required * and "(optional)" inside the option label.
	const copy = label.cloneNode( true ) as HTMLElement;
	copy.querySelectorAll( '.required, .optional, abbr, input' ).forEach( ( mark ) => mark.remove() );

	return ( copy.textContent ?? '' ).replace( /\s+/g, ' ' ).trim();
}

function optionLabel( control: HTMLInputElement ): Element | null {
	const wrapping = control.closest( 'label' );

	if ( wrapping ) {
		return wrapping;
	}

	if ( control.id === '' ) {
		return null;
	}

	const labels = [ ...document.querySelectorAll< HTMLLabelElement >( `label[for="${ CSS.escape( control.id ) }"]` ) ];

	return labels.find( ( label ) => label.classList.contains( 'radio' ) || label.classList.contains( 'checkbox' ) ) ?? labels[ 0 ] ?? null;
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
