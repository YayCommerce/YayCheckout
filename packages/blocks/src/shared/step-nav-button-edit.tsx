import { RichText, useBlockProps } from '@wordpress/block-editor';

export interface StepNavButtonAttributes {
	buttonText: string;
}

export interface StepNavButtonEditProps {
	attributes: StepNavButtonAttributes;
	setAttributes: ( attributes: Partial< StepNavButtonAttributes > ) => void;
}

interface Props extends StepNavButtonEditProps {
	className: string;
	placeholder: string;
}

/** Continue and Back. A div, not a button: typing Space inside a button would click it. */
export function StepNavButtonEdit( { className, placeholder, attributes, setAttributes }: Props ) {
	const blockProps = useBlockProps( { className } );

	return (
		<RichText
			{ ...blockProps }
			tagName="div"
			value={ attributes.buttonText }
			onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
			placeholder={ placeholder }
			allowedFormats={ [] }
			withoutInteractiveFormatting
		/>
	);
}
