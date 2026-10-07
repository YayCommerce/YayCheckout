import { AddressSectionEdit } from '../shared/address-section-edit';

interface BillingAttributes {
	title: string;
	showTitle: boolean;
}

interface EditProps {
	attributes: BillingAttributes;
	setAttributes: ( attributes: Partial< BillingAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<AddressSectionEdit
			section="billing"
			attributes={ attributes }
			setAttributes={ setAttributes }
		/>
	);
}
