import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import { EMAIL_LABEL } from './defaults';
import { FieldTextControls } from '../shared/field-text-controls';
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
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<FieldTextControls
						label={ label }
						placeholder={ placeholder }
						description={ description }
						setAttributes={ setAttributes }
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
					labelPlaceholder={ EMAIL_LABEL }
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
