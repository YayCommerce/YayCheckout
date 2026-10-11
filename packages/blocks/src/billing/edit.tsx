import { AddressSectionEdit } from '../shared/address-section-edit';
import type { BlockGapStyles } from '../shared/block-gap';

interface BillingAttributes {
	title: string;
	showTitle: boolean;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: BillingAttributes;
	clientId: string;
	setAttributes: ( attributes: Partial< BillingAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	return (
		<AddressSectionEdit
			section="billing"
			attributes={ attributes }
			setAttributes={ setAttributes }
			clientId={ clientId }
		/>
	);
}
