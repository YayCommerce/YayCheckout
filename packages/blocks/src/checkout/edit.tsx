import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { addressTemplate } from '../shared/address-template';
import { useLabelMode } from '../shared/checkout-scope';
import billingMeta from '../billing/block.json';
import differentAddressMeta from '../different-address/block.json';
import emailMeta from '../email/block.json';
import paymentMeta from '../payment/block.json';
import placeOrderMeta from '../place-order/block.json';
import shippingMeta from '../shipping/block.json';
import shippingMethodsMeta from '../shipping-methods/block.json';
import termsMeta from '../terms/block.json';
import wcHookMeta from '../wc-hook/block.json';

/**
 * What a newly inserted Checkout holds. A template only fills a block that has no
 * inner blocks, so saved layouts are untouched.
 */
const defaultTemplate = () => [
	// WooCommerce fires this before its form; the Checkout block fires no hook itself, so plugin notices print here.
	[ wcHookMeta.name, { hook: 'woocommerce_before_checkout_form' } ],
	[ emailMeta.name ],
	[ billingMeta.name, {}, addressTemplate( 'billing' ) ],
	[ differentAddressMeta.name ],
	[ shippingMeta.name, {}, addressTemplate( 'shipping' ) ],
	[ shippingMethodsMeta.name ],
	[ paymentMeta.name ],
	[ termsMeta.name ],
	[ placeOrderMeta.name ],
];

export default function Edit() {
	// The inputs.label token's class. Mirrors LayoutDesign::has_floating_labels() and has_hidden_labels() in PHP.
	const labelMode = useLabelMode();
	const blockProps = useBlockProps( {
		className: 'yaycheckout-checkout' + ( labelMode ? ` yaycheckout-checkout--${ labelMode }-labels` : '' ),
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: defaultTemplate(),
		templateLock: false,
	} );

	return <div { ...innerBlocksProps } />;
}
