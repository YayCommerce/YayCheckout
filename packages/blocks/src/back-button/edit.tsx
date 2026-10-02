import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface BackButtonAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: BackButtonAttributes;
	setAttributes: ( attributes: Partial< BackButtonAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { buttonText } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-back-button', type: 'button' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Back button', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Button text', 'yaycheckout' ) }
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<button { ...blockProps }>{ buttonText || __( 'Back', 'yaycheckout' ) }</button>
		</>
	);
}
