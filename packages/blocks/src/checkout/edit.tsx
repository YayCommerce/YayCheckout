import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { editorData } from '../shared/editor-data';
import { addressTemplate } from '../shared/address-template';
import billingMeta from '../billing/block.json';
import differentAddressMeta from '../different-address/block.json';
import emailMeta from '../email/block.json';
import paymentMeta from '../payment/block.json';
import placeOrderMeta from '../place-order/block.json';
import shippingMeta from '../shipping/block.json';
import shippingMethodsMeta from '../shipping-methods/block.json';
import termsMeta from '../terms/block.json';

/**
 * What a newly inserted Checkout holds. A template only fills a block that has no
 * inner blocks, so saved layouts are untouched.
 */
const defaultTemplate = () => [
	[ emailMeta.name ],
	[ billingMeta.name, {}, addressTemplate( 'billing' ) ],
	[ differentAddressMeta.name ],
	[ shippingMeta.name, {}, addressTemplate( 'shipping' ) ],
	[ shippingMethodsMeta.name ],
	[ paymentMeta.name ],
	[ termsMeta.name ],
	[ placeOrderMeta.name ],
];

/** The wrapper class for the inputs.label token, as the merchant is editing it. Mirrors LayoutDesign::has_floating_labels() and has_hidden_labels() in PHP. */
function useLabelClass(): string {
	const metaKey = editorData.design?.metaKey ?? '';

	return useSelect(
		( selectStore ) => {
			const meta = selectStore( editorStore ).getEditedPostAttribute( 'meta' ) as
				| Record< string, { inputs?: { label?: unknown } } | undefined >
				| undefined;

			const label = '' !== metaKey ? meta?.[ metaKey ]?.inputs?.label : undefined;

			if ( 'floating' === label ) {
				return ' yaycheckout-checkout--floating-labels';
			}

			return 'hidden' === label ? ' yaycheckout-checkout--hidden-labels' : '';
		},
		[ metaKey ]
	);
}

export default function Edit() {
	const labelClass = useLabelClass();
	const blockProps = useBlockProps( {
		className: 'yaycheckout-checkout' + labelClass,
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: defaultTemplate(),
		templateLock: false,
	} );

	return <div { ...innerBlocksProps } />;
}
