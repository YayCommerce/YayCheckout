/**
 * Storefront whole-item click for shipping and payment methods.
 *
 * The label already selects its radio. This covers the item's own padding and gaps.
 * A click on any other child (a gateway's .payment_box, pickup fields a shipping plugin prints)
 * leaves the selection alone.
 */

import { bindOnce } from './bind-once';

const ITEM = [
	'.yaycheckout-checkout .yaycheckout-shipping-methods__option',
	'.yaycheckout-checkout .yaycheckout-payment #payment .wc_payment_methods > li',
].join( ', ' );

export function bindMethodItemClick(): void {
	// Both blocks' view scripts call this, so the first one binds.
	bindOnce( 'data-yaycheckout-method-click-bound', () => document.addEventListener( 'click', onClick ) );
}

function onClick( event: MouseEvent ): void {
	const target = event.target;

	if ( ! ( target instanceof Element ) || ! target.matches( ITEM ) ) {
		return;
	}

	// A single shipping rate posts a hidden input and a notice row has none, so neither has a radio.
	const radio = target.querySelector< HTMLInputElement >( ':scope > input[type="radio"]' );

	// click() fires click and change, which Woo's checkout.js listens for on payment_method and shipping_method.
	if ( radio && ! radio.disabled && ! radio.checked ) {
		radio.click();
	}
}
