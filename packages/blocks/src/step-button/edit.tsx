import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

interface StepButtonAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: StepButtonAttributes;
	setAttributes: ( attributes: Partial< StepButtonAttributes > ) => void;
}

/** A div, not a button: typing Space inside a button would click it. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-step-button' } );

	return (
		<RichText
			{ ...blockProps }
			tagName="div"
			value={ attributes.buttonText }
			onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
			placeholder={ __( 'Continue', 'yaycheckout' ) }
			allowedFormats={ [] }
			withoutInteractiveFormatting
		/>
	);
}
