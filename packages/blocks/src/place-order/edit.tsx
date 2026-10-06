import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { previewButtonStyle } from '../shared/preview-styles';

interface PlaceOrderAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
	setAttributes: ( attributes: Partial< PlaceOrderAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { buttonText } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-place-order' } );
	const label = buttonText || __( 'Place order', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Place order', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Button text', 'yaycheckout' ) }
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<button
					type="button"
					disabled
					style={ { ...previewButtonStyle, display: 'block', width: '100%', padding: '12px 16px' } }
				>
					{ label }
				</button>
			</div>
		</>
	);
}
