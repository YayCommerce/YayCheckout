import { AddressSectionEdit } from '../shared/address-section-edit';

interface BillingAttributes {
	title: string;
	showTitle: boolean;
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
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
		/>
	);
}
