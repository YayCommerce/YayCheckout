import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import { RequiredIndicatorControls } from '../shared/required-indicator-controls';
import { DisclosureControls, DisclosurePreview, defaultDisclosureText, disclosureMode, type DisclosureMode } from '../shared/disclosure';

// The note's fixed WooCommerce key; render.php prints the same.
const FIELD_KEY = 'order_comments';

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
	const labelPlaceholder = __( 'Order notes', 'yaycheckout' );
	const mode = disclosureMode( FIELD_KEY, required, disclosure );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order note', 'yaycheckout' ) }>
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
						required={ required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						onRequiredChange={ ( value ) => setAttributes( { required: value } ) }
						onRequiredMarkChange={ ( value ) => setAttributes( { requiredMark: value } ) }
						onOptionalTextChange={ ( value ) => setAttributes( { optionalText: value } ) }
					/>
					<DisclosureControls
						fieldKey={ FIELD_KEY }
						label={ label || labelPlaceholder }
						required={ required }
						disclosure={ disclosure }
						disclosureText={ disclosureText }
						setAttributes={ setAttributes }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<DisclosurePreview
					mode={ mode }
					text={ disclosureText || defaultDisclosureText( FIELD_KEY, label || labelPlaceholder, mode ) }
				>
					<FieldPreview
						label={ label }
						onLabelChange={ ( value ) => setAttributes( { label: value } ) }
						labelPlaceholder={ labelPlaceholder }
						type="textarea"
						required={ required }
						requiredMark={ requiredMark }
						optionalText={ optionalText }
						placeholder={
							placeholder || __( 'Notes about your order, e.g. special notes for delivery.', 'yaycheckout' )
						}
						description={ description }
						inheritLabelFont
					/>
				</DisclosurePreview>
			</div>
		</>
	);
}
