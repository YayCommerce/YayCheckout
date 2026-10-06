import { AddressSectionEdit } from '../shared/address-section-edit';

interface ShippingAttributes {
	title: string;
	showTitle: boolean;
	shipToDifferent: boolean;
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
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
		/>
	);
}
