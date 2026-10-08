import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';

interface EmailAttributes {
	label: string;
	placeholder: string;
	description: string;
	required: boolean;
	requiredMark: string;
}

interface EditProps {
	attributes: EmailAttributes;
	setAttributes: ( attributes: Partial< EmailAttributes > ) => void;
}

/**
 * The Email address block: billing_email lives on this block, not on a Field.
 * It is always required; that rule lives in RequiredFieldOverlay::is_required().
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, placeholder, description = '', required, requiredMark = '' } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-email' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Email address', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ decodeEntities( label ) }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'yaycheckout' ) }
						value={ placeholder }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
					<TextControl
						label={ __( 'Help text', 'yaycheckout' ) }
						help={ __( 'Shown under the field while the shopper fills it in.', 'yaycheckout' ) }
						value={ description }
						onChange={ ( value ) => setAttributes( { description: value } ) }
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
					label={ label }
					onLabelChange={ ( value ) => setAttributes( { label: value } ) }
					labelPlaceholder={ __( 'Email address', 'yaycheckout' ) }
					type="email"
					required
					requiredMark={ requiredMark }
					placeholder={ placeholder }
					description={ description }
					inheritLabelFont
				/>
			</div>
		</>
	);
}
