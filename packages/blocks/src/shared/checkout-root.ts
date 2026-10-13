export const CHECKOUT_SELECTOR = '.yaycheckout-checkout';

/**
 * The Checkout around an element, or the page's Checkout when the element sits outside it
 * (Steps Progress and the step buttons may). Checkout is `multiple: false`, so a page holds one.
 */
export function checkoutFor( element: Element ): Element | null {
	return element.closest( CHECKOUT_SELECTOR ) ?? document.querySelector( CHECKOUT_SELECTOR );
}

/**
 * Whether a step can be reopened: only a completed one. The one rule for a click on a step's
 * header (steps/view.ts), Steps Progress and Go to Step.
 */
export function canReopenStep( step: HTMLElement | null | undefined ): boolean {
	return step?.dataset.state === 'complete';
}

/** The open Step inside `root`: the one steps/view.ts marks `data-state="current"`; null while none is. */
export function currentStep( root: ParentNode ): HTMLElement | null {
	return root.querySelector< HTMLElement >( '.yaycheckout-step[data-state="current"]' );
}

/** Asks Steps (steps/view.ts onReopen()) to open step `step`, a 1-based data-step value. */
export function reopenStep( checkout: Element, step: string ): void {
	checkout.dispatchEvent( new CustomEvent( 'yaycheckout:step-reopen', { bubbles: true, detail: { step } } ) );
}
