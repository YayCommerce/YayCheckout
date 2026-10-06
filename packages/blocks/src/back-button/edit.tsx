import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

interface BackButtonAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: BackButtonAttributes;
	setAttributes: ( attributes: Partial< BackButtonAttributes > ) => void;
}

/** A div, not a button: typing Space inside a button would click it. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-back-button' } );

	return (
		<RichText
			{ ...blockProps }
			tagName="div"
			value={ attributes.buttonText }
			onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
			placeholder={ __( 'Back', 'yaycheckout' ) }
			allowedFormats={ [] }
			withoutInteractiveFormatting
		/>
	);
}
