import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

interface PlaceOrderAttributes {
	buttonText?: string;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
	setAttributes: ( attributes: Partial< PlaceOrderAttributes > ) => void;
}

/** The button is the block's root element, so its style supports land on it. Mirrors the class and default label render.php prints on the <button>. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<RichText
			{ ...useBlockProps( { className: 'yaycheckout-place-order wp-element-button' } ) }
			tagName="div"
			value={ attributes.buttonText ?? '' }
			onChange={ ( buttonText: string ) => setAttributes( { buttonText: buttonText.replace( /\s*\n\s*/g, ' ' ) } ) }
			placeholder={ __( 'Place order', 'yaycheckout' ) }
			// Plain text on one line (pasted line breaks become spaces): the label also goes into value and data-value.
			__unstableDisableFormats
			disableLineBreaks
		/>
	);
}
