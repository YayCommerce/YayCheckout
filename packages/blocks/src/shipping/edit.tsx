import { AddressSectionEdit } from '../shared/address-section-edit';
import type { BlockGapStyles } from '../shared/block-gap';

interface ShippingAttributes {
	title: string;
	showTitle: boolean;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: ShippingAttributes;
	clientId: string;
	setAttributes: ( attributes: Partial< ShippingAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	return (
		<AddressSectionEdit
			section="shipping"
			attributes={ attributes }
			setAttributes={ setAttributes }
			clientId={ clientId }
		/>
	);
}
