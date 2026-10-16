import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { LIVE_VALUE_FORMAT } from '../live-value/constants';
import './editor.css';

interface PlaceOrderAttributes {
	buttonText?: string;
	spread: boolean;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
	setAttributes: ( attributes: Partial< PlaceOrderAttributes > ) => void;
}

/** The button is the block's root element, so its style supports land on it. Mirrors the class and default label render.php prints on the <button>. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	// Core pads a label that ends in a live value with a U+FEFF text node, for the caret, and editor.css keeps it out
	// of the row's layout. The label holds no other format, so a closing </span> at the end is a live value.
	const endsWithValue = /<\/span>$/.test( attributes.buttonText ?? '' );
	const blockProps = useBlockProps( {
		className:
			'yaycheckout-place-order wp-element-button' +
			( attributes.spread ? ' yaycheckout-place-order--spread' : '' ) +
			( endsWithValue ? ' yaycheckout-place-order--ends-with-value' : '' ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Spread', 'yaycheckout' ) }
						help={ __( 'Puts the label and the live values at opposite ends of the button.', 'yaycheckout' ) }
						checked={ attributes.spread }
						onChange={ ( spread: boolean ) => setAttributes( { spread } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
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
		</>
	);
}
