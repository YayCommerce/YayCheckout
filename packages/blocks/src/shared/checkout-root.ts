export const CHECKOUT_SELECTOR = '.yaycheckout-checkout';

/**
 * The Checkout around an element, or the page's Checkout when the element sits outside it
 * (Progress and the step buttons may). Checkout is `multiple: false`, so a page holds one.
 */
export function checkoutFor( element: Element ): Element | null {
	return element.closest( CHECKOUT_SELECTOR ) ?? document.querySelector( CHECKOUT_SELECTOR );
}
