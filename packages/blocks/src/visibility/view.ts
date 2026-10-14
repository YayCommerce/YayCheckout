/**
 * Keeps each conditional block's visibility in step with the checkout: re-decided when a step opens, after
 * every update_order_review, which swaps the footer's state element, after a country change, and when
 * Field Value fires REFRESH_EVENT. BlockVisibility printed each block as it is with step 1 open; without
 * this script the page stays that way.
 *
 * PHP owns what "empty" means for a watchable block: BlockEmptiness marks wrappers and CheckoutFragments
 * marks the fragments it swaps; for a Field, WooCommerce's address-i18n.js (a dependency) hides the row for
 * the country. This script only reads those markers.
 */

import { currentStep } from '../shared/checkout-root';
import { onDomReady } from '../shared/dom-ready';
import { jquery } from '../shared/jquery';
import { readJsonElement } from '../shared/json-element';
import {
	BLOCK_ID_ATTR,
	blockIds,
	DATA_ATTR,
	EMPTY_ATTR,
	emptyIds,
	HIDDEN_CLASS,
	isVisible,
	LIVE_ATTR,
	LOCALE_ATTR,
	normalize,
	REFRESH_EVENT,
	STATE_ID,
	type VisibilityState,
} from '../shared/visibility-rules';

type State = VisibilityState & { step_count: number };

/** The footer's state element; null when it is missing or unreadable. */
function readState(): State | null {
	return readJsonElement< State >( STATE_ID );
}

/** The open step: the current Step in the first Steps block, or the last step while none is current (all complete). */
function openStep( stepCount: number ): number {
	const steps = document.querySelector( '.yaycheckout-steps' );
	const current = steps ? currentStep( steps ) : null;
	const step = Number( current?.dataset.step );

	return current && Number.isInteger( step ) && step > 0 ? step : stepCount;
}

// A swapped fragment carries the fresh marker; its block wrapper still holds the first paint's.
function syncLiveMarkers(): void {
	document.querySelectorAll( `[${ LIVE_ATTR }]` ).forEach( ( fragment ) => {
		fragment.closest( `[${ BLOCK_ID_ATTR }]` )?.toggleAttribute( EMPTY_ATTR, fragment.hasAttribute( EMPTY_ATTR ) );
	} );
}

// address-i18n.js hides a Field's row with .hide() when the country's locale hides the field. The row's own
// display is read, not whether it is visible, which a hidden ancestor would make false for every row.
function syncLocaleMarkers(): void {
	document.querySelectorAll< HTMLElement >( `[${ LOCALE_ATTR }][${ BLOCK_ID_ATTR }]` ).forEach( ( wrapper ) => {
		const row = wrapper.matches( '.form-row[id$="_field"]' )
			? wrapper
			: wrapper.querySelector< HTMLElement >( '.form-row[id$="_field"]' );

		if ( row ) {
			wrapper.toggleAttribute( EMPTY_ATTR, getComputedStyle( row ).display === 'none' );
		}
	} );
}

let state: State | null = null;

function apply(): void {
	const current = state;

	// A state that didn't parse leaves the blocks as they are.
	if ( ! current ) {
		return;
	}

	syncLiveMarkers();
	syncLocaleMarkers();

	const stepCount = Number( current.step_count ) || 0;
	const step = openStep( stepCount );

	document.querySelectorAll< HTMLElement >( `[${ DATA_ATTR }]` ).forEach( ( block ) => {
		let setting;

		try {
			setting = normalize( JSON.parse( block.getAttribute( DATA_ATTR ) ?? '' ) );
		} catch {
			setting = null;
		}

		if ( setting ) {
			const empty = emptyIds( document, blockIds( setting ) );

			block.classList.toggle( HIDDEN_CLASS, ! isVisible( setting, current, step, stepCount, empty ) );
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

	// Bound after address-i18n.js (a dependency), so its show/hide has already run on country_to_state_changing.
	jquery( document.body )?.on( `country_to_state_changing ${ REFRESH_EVENT }`, apply );
} );
