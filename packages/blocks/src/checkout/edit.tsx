import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { billingAddressFields, shippingAddressFields, type AddressFieldDef } from '../shared/address-fields';
import billingMeta from '../billing/block.json';
import fieldMeta from '../field/block.json';
import emailMeta from '../email/block.json';
import paymentMeta from '../payment/block.json';
import placeOrderMeta from '../place-order/block.json';
import shippingMeta from '../shipping/block.json';
import shippingMethodsMeta from '../shipping-methods/block.json';

/**
 * An address section seeded with the required address fields, as templates/default-layout.php
 * seeds it, so a new section is not an empty container.
 */
function addressSection( name: string, fields: AddressFieldDef[] ) {
	return [
		name,
		{},
		fields
			.filter( ( { required } ) => required )
			.map( ( { key, type, label, required } ) => [
				fieldMeta.name,
				{ fieldKey: key, type, label, required },
			] ),
	];
}

/**
 * What a newly inserted Checkout holds. A template only fills a block that has no
 * inner blocks, so saved layouts are untouched.
 */
const defaultTemplate = () => [
	[ emailMeta.name ],
	addressSection( billingMeta.name, billingAddressFields() ),
	addressSection( shippingMeta.name, shippingAddressFields() ),
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
