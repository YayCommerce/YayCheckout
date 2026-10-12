export const CHECKOUT_SELECTOR = '.yaycheckout-checkout';

/**
 * The Checkout around an element, or the page's Checkout when the element sits outside it
 * (Progress and the step buttons may). Checkout is `multiple: false`, so a page holds one.
 */
export function checkoutFor( element: Element ): Element | null {
	return element.closest( CHECKOUT_SELECTOR ) ?? document.querySelector( CHECKOUT_SELECTOR );
}

/** The state Steps gave that step (`current`, `locked` or `complete`); undefined when Checkout has no such step. The first step with that number wins. */
export function stepState( checkout: Element, step: string ): string | undefined {
	return checkout.querySelector< HTMLElement >( `.yaycheckout-step[data-step="${ CSS.escape( step ) }"]` )?.dataset.state;
}

/** Asks Steps (steps/view.ts) to open that step. It re-checks the open step first and stays on it when that fails. */
export function reopenStep( checkout: Element, step: string ): void {
	checkout.dispatchEvent( new CustomEvent( 'yaycheckout:step-reopen', { bubbles: true, detail: { step } } ) );
}
