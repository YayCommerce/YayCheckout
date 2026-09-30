/**
 * Storefront open/close for yaycheckout/accordion.
 *
 * Continue checks only the open step. The rest of the form is still empty
 * and required, so a form-wide checkValidity() would block step 1.
 */

const STEP = '.yaycheckout-accordion-step';
const CURRENT = 'current';
const LOCKED = 'locked';
const COMPLETE = 'complete';

type Control = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

function boot(): void {
	if ( document.documentElement.hasAttribute( 'data-yaycheckout-accordion-bound' ) ) {
		return;
	}

	document.documentElement.setAttribute( 'data-yaycheckout-accordion-bound', '' );
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

	const step = target.closest< HTMLElement >( STEP );

	if ( ! step || step.dataset.state !== COMPLETE || target.closest( '.yaycheckout-accordion-step__body' ) ) {
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
	const accordion = step.closest( '.yaycheckout-accordion' );

	if ( ! accordion ) {
		return;
	}

	const blocked = [ ...accordion.querySelectorAll< HTMLElement >( STEP ) ].some(
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
	const summary = step.querySelector< HTMLElement >( '.yaycheckout-accordion-step__summary' );

	if ( summary ) {
		summary.hidden = ! done || summary.textContent === '';
	}

	const edit = step.querySelector< HTMLButtonElement >( '.yaycheckout-accordion-step__edit' );

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

	let sibling = nextStep( step );

	while ( sibling && sibling.dataset.state === COMPLETE ) {
		sibling = nextStep( sibling );
	}

	return sibling !== null;
}

function syncStepButtons(): void {
	document.querySelectorAll< HTMLButtonElement >( '.yaycheckout-step-button' ).forEach( ( button ) => {
		const step = stepForButton( button );

		button.hidden = ! step || ! canAdvance( step );
	} );
}

function writeSummary( step: HTMLElement, line: string ): void {
	const summary = step.querySelector< HTMLElement >( '.yaycheckout-accordion-step__summary' );

	if ( summary ) {
		summary.textContent = line;
	}
}

function openNext( step: HTMLElement ): void {
	let sibling = nextStep( step );

	while ( sibling && sibling.dataset.state === COMPLETE ) {
		sibling = nextStep( sibling );
	}

	if ( sibling && sibling.dataset.state === LOCKED ) {
		setState( sibling, CURRENT );
	}
}

function nextStep( step: HTMLElement ): HTMLElement | null {
	const sibling = step.nextElementSibling;

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
	const accordion = step.closest< HTMLElement >( '.yaycheckout-accordion' );

	return accordion?.dataset[ key ] ?? '';
}

function summaryText( step: HTMLElement ): string {
	const chosen = [ ...step.querySelectorAll< HTMLInputElement >( 'input[type="radio"]:checked' ) ]
		.map( ( radio ) => labelFor( radio ) )
		.filter( ( value ) => value !== '' );

	if ( chosen.length > 0 ) {
		return chosen.join( ' · ' );
	}

	return visibleControls( step )
		.map( ( control ) => textOf( control ) )
		.filter( ( value ) => value !== '' )
		.join( ', ' );
}

function textOf( control: Control ): string {
	if ( control instanceof HTMLSelectElement ) {
		return control.value.trim() === '' ? '' : control.selectedOptions[ 0 ]?.text.trim() ?? '';
	}

	if ( control instanceof HTMLInputElement && ( control.type === 'radio' || control.type === 'checkbox' ) ) {
		return '';
	}

	return control.value.trim();
}

function labelFor( radio: HTMLInputElement ): string {
	const label = radio.closest( 'label' ) ?? ( radio.id ? document.querySelector( `label[for="${ CSS.escape( radio.id ) }"]` ) : null );
	const text = label?.textContent ?? radio.value;

	return text.replace( /\s+/g, ' ' ).trim();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
