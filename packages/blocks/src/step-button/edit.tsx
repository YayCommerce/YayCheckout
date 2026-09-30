import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface StepButtonAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: StepButtonAttributes;
	setAttributes: ( attributes: Partial< StepButtonAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { buttonText } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-step-button', type: 'button' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Step button', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Button text', 'yaycheckout' ) }
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<button { ...blockProps }>{ buttonText || __( 'Continue', 'yaycheckout' ) }</button>
		</>
	);
}
