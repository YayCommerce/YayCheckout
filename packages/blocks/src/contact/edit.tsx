import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';

interface ContactAttributes {
	label: string;
	placeholder: string;
	required: boolean;
}

interface EditProps {
	attributes: ContactAttributes;
	setAttributes: ( attributes: Partial< ContactAttributes > ) => void;
}

/**
 * Contact is a leaf: billing_email lives on this block, not an inner Field.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, placeholder, required } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-contact' } );
	const canvasLabel = label || __( 'Email address', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Contact', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'yaycheckout' ) }
						value={ placeholder }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'yaycheckout' ) }
						checked={ required }
						onChange={ ( value ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<FieldPreview
					label={ canvasLabel }
					type="email"
					required={ required }
					placeholder={ placeholder }
				/>
			</div>
		</>
	);
}
