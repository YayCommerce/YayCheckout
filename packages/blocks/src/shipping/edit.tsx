import { AddressSectionEdit, type AddressSectionAttributes } from '../shared/address-section-edit';

type ShippingAttributes = AddressSectionAttributes;

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
