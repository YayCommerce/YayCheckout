import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface PlaceOrderAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
	setAttributes: ( attributes: Partial< PlaceOrderAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-place-order' } );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="div"
				className="yaycheckout-place-order__button"
				value={ attributes.buttonText }
				onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
				placeholder={ __( 'Place order', 'yaycheckout' ) }
				allowedFormats={ [] }
				withoutInteractiveFormatting
			/>
		</div>
	);
}
