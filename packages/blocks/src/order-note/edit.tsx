import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import { FieldTextControls } from '../shared/field-text-controls';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';
import { DisclosureControls, DisclosurePreview, disclosureMode, type DisclosureMode } from '../shared/disclosure';
import { ORDER_NOTE_KEY, ORDER_NOTE_LABEL, ORDER_NOTE_PLACEHOLDER } from './defaults';

interface OrderNoteAttributes {
	label: string;
	placeholder: string;
	description: string;
	required: boolean;
	requiredMark: string;
	optionalText: string;
	disclosure?: DisclosureMode;
	disclosureText: string;
}

interface EditProps {
	attributes: OrderNoteAttributes;
	setAttributes: ( attributes: Partial< OrderNoteAttributes > ) => void;
}

/** A Field for WooCommerce's order_comments; empty label and placeholder fall back to WooCommerce's, as in render.php. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const {
		label,
		placeholder,
		description = '',
		required,
		requiredMark = '',
		optionalText = '',
		disclosure,
		disclosureText = '',
	} = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-order-note' } );
	const labelPlaceholder = ORDER_NOTE_LABEL;
	const mode = disclosureMode( ORDER_NOTE_KEY, required, disclosure );

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
						required={ required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						onRequiredChange={ ( value ) => setAttributes( { required: value } ) }
						onRequiredMarkChange={ ( value ) => setAttributes( { requiredMark: value } ) }
						onOptionalTextChange={ ( value ) => setAttributes( { optionalText: value } ) }
					/>
					<DisclosureControls
						fieldKey={ ORDER_NOTE_KEY }
						label={ label || labelPlaceholder }
						required={ required }
						disclosure={ disclosure }
						disclosureText={ disclosureText }
						setAttributes={ setAttributes }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<DisclosurePreview mode={ mode } fieldKey={ ORDER_NOTE_KEY } label={ label || labelPlaceholder } disclosureText={ disclosureText }>
					<FieldPreview
						label={ label }
						onLabelChange={ ( value ) => setAttributes( { label: value } ) }
						labelPlaceholder={ labelPlaceholder }
						type="textarea"
						required={ required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						placeholder={ placeholder || ORDER_NOTE_PLACEHOLDER }
						description={ description }
						inheritLabelFont
					/>
				</DisclosurePreview>
			</div>
		</>
	);
}
