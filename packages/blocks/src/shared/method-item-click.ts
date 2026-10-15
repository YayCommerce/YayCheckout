/**
 * Storefront whole-item click for shipping and payment methods.
 *
 * The label already selects its radio. This covers the rest of an unselected item: its padding
 * and gaps, and a gateway's .payment_box that "Always show descriptions" keeps visible.
 * In the selected item, a click on another child (its .payment_box, pickup fields a shipping
 * plugin prints) changes nothing, since the radio is already checked.
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
	const item = target instanceof Element ? target.closest( ITEM ) : null;

	if ( ! item ) {
		return;
	}

	// The item's own label selects the radio by itself.
	if ( item.querySelector( ':scope > label' )?.contains( target as Node ) ) {
		return;
	}

	// A single shipping rate posts a hidden input and a notice row has none, so neither has a radio.
	const radio = item.querySelector< HTMLInputElement >( ':scope > input[type="radio"]' );

	// click() fires click and change, which Woo's checkout.js listens for on payment_method and shipping_method.
	if ( radio && ! radio.disabled && ! radio.checked ) {
		radio.click();
	}
}
