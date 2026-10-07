import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { addressTemplate } from '../shared/address-template';
import billingMeta from '../billing/block.json';
import emailMeta from '../email/block.json';
import paymentMeta from '../payment/block.json';
import placeOrderMeta from '../place-order/block.json';
import shippingMeta from '../shipping/block.json';
import shippingMethodsMeta from '../shipping-methods/block.json';

/**
 * What a newly inserted Checkout holds. A template only fills a block that has no
 * inner blocks, so saved layouts are untouched.
 */
const defaultTemplate = () => [
	[ emailMeta.name ],
	[ billingMeta.name, {}, addressTemplate( 'billing' ) ],
	[ shippingMeta.name, {}, addressTemplate( 'shipping' ) ],
	[ shippingMethodsMeta.name ],
	[ paymentMeta.name ],
	[ placeOrderMeta.name ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-checkout' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: defaultTemplate(),
		templateLock: false,
	} );

	return <div { ...innerBlocksProps } />;
}
