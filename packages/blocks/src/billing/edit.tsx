import { AddressSectionEdit, type AddressSectionAttributes } from '../shared/address-section-edit';

type BillingAttributes = AddressSectionAttributes;

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
