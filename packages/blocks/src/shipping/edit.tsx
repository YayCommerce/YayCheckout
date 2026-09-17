import { AddressSectionEdit } from '../shared/address-section-edit';

interface ShippingAttributes {
	title: string;
	showTitle: boolean;
	shipToDifferent: boolean;
}

interface EditProps {
	attributes: ShippingAttributes;
	setAttributes: ( attributes: Partial< ShippingAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<AddressSectionEdit
			section="shipping"
			attributes={ attributes }
			setAttributes={ setAttributes }
		/>
	);
}
