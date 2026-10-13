import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { LIVE_VALUE_FORMAT } from '../live-value/constants';

interface PlaceOrderAttributes {
	buttonText?: string;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
	setAttributes: ( attributes: Partial< PlaceOrderAttributes > ) => void;
}

/** The button is the block's root element, so its style supports land on it. Mirrors the class and default label render.php prints on the <button>. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( {
		className: 'yaycheckout-place-order wp-element-button',
	} );

	return (
		<RichText
			{ ...blockProps }
			tagName="div"
			value={ attributes.buttonText ?? '' }
			onChange={ ( buttonText: string ) =>
				setAttributes( { buttonText: buttonText.replace( /\s*\n\s*/g, ' ' ) } )
			}
			placeholder={ __( 'Place order', 'yaycheckout' ) }
			// One line (pasted line breaks become spaces) whose only format is Live value: render.php fills it, then
			// copies the plain text into value and data-value.
			allowedFormats={ [ LIVE_VALUE_FORMAT ] }
			disableLineBreaks
		/>
	);
}
