/**
 * Keeps each conditional block's visibility in step with the checkout: re-decided when a step opens and
 * after every update_order_review, which swaps the footer's state element. BlockVisibility::filter_block()
 * printed each block as it is with step 1 open; without this script the page stays that way.
 */

import { onDomReady } from '../shared/dom-ready';
import { jquery } from '../shared/jquery';
import { DATA_ATTR, HIDDEN_CLASS, isVisible, normalize, STATE_ID, type VisibilityState } from '../shared/visibility-rules';

type State = VisibilityState & { step_count: number };

/** The footer's state element; null when it is missing or unreadable. */
function readState(): State | null {
	try {
		const parsed: unknown = JSON.parse( document.getElementById( STATE_ID )?.textContent ?? '' );

		return parsed !== null && typeof parsed === 'object' ? ( parsed as State ) : null;
	} catch {
		return null;
	}
}

/** The open step: the current Step in the first Steps block, or the last step while none is current (all complete). */
function openStep( stepCount: number ): number {
	const current = document.querySelector( '.yaycheckout-steps' )?.querySelector< HTMLElement >( '.yaycheckout-step[data-state="current"]' );
	const step = Number( current?.dataset.step );

	return current && Number.isInteger( step ) && step > 0 ? step : stepCount;
}

let state: State | null = null;

function apply(): void {
	// A state that didn't parse leaves the blocks as they are.
	if ( ! state ) {
		return;
	}

	const stepCount = Number( state.step_count ) || 0;
	const step = openStep( stepCount );

	document.querySelectorAll< HTMLElement >( `[${ DATA_ATTR }]` ).forEach( ( block ) => {
		let setting;

		try {
			setting = normalize( JSON.parse( block.getAttribute( DATA_ATTR ) ?? '' ) );
		} catch {
			setting = null;
		}

		if ( setting ) {
			block.classList.toggle( HIDDEN_CLASS, ! isVisible( setting, state as State, step, stepCount ) );
		}
	} );
}

onDomReady( () => {
	state = readState();
	apply();

	document.addEventListener( 'yaycheckout:step-change', apply );

	jquery( document.body )?.on( 'updated_checkout', () => {
		state = readState() ?? state;
		apply();
	} );
} );
