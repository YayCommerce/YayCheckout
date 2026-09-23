import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';

interface ContactAttributes {
	label: string;
	placeholder: string;
	required: boolean;
	requiredMark: string;
}

interface EditProps {
	attributes: ContactAttributes;
	setAttributes: ( attributes: Partial< ContactAttributes > ) => void;
}

/**
 * Contact is a leaf: billing_email lives on this block, not an inner Field.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, placeholder, required, requiredMark = '' } = attributes;
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
					<RequiredIndicatorControls
						required
						requiredMark={ requiredMark }
						optionalText=""
						requiredLocked
						requiredHelp={ __( 'Email is always required.', 'yaycheckout' ) }
						onRequiredMarkChange={ ( value ) => setAttributes( { requiredMark: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<FieldPreview
					label={ canvasLabel }
					type="email"
					required
					requiredMark={ requiredMark }
					placeholder={ placeholder }
				/>
			</div>
		</>
	);
}
