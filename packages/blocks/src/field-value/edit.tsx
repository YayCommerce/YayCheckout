import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { billingAddressFields, shippingAddressFields, type AddressFieldDef } from '../shared/address-fields';
import './editor.css';

interface FieldValueAttributes {
	fieldKey: string;
	placeholder: string;
	showLabel: boolean;
}

interface EditProps {
	attributes: FieldValueAttributes;
	setAttributes: ( attributes: Partial< FieldValueAttributes > ) => void;
}

function fieldGroups(): { label: string; fields: AddressFieldDef[] }[] {
	return [
		{
			label: __( 'Billing', 'yaycheckout' ),
			fields: [
				{ key: 'billing_email', type: 'email', label: __( 'Email address', 'yaycheckout' ), required: true },
				...billingAddressFields(),
			],
		},
		{ label: __( 'Shipping', 'yaycheckout' ), fields: shippingAddressFields() },
	];
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { fieldKey, placeholder, showLabel } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-field-value' } );
	const groups = fieldGroups();
	const fieldLabel = groups.flatMap( ( group ) => group.fields ).find( ( field ) => field.key === fieldKey )?.label ?? '';

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field value', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Field', 'yaycheckout' ) }
						value={ fieldKey }
						// A placeholder is written for one field ("email@____"), so a new field starts from its own name.
						onChange={ ( value: string ) => setAttributes( { fieldKey: value, placeholder: '' } ) }
						__nextHasNoMarginBottom
					>
						{ groups.map( ( group ) => (
							<optgroup key={ group.label } label={ group.label }>
								{ group.fields.map( ( field ) => (
									<option key={ field.key } value={ field.key }>
										{ field.label }
									</option>
								) ) }
							</optgroup>
						) ) }
					</SelectControl>
					<ToggleControl
						label={ __( 'Show label', 'yaycheckout' ) }
						help={ __( "Prints the field's name above its value.", 'yaycheckout' ) }
						checked={ showLabel }
						onChange={ ( value: boolean ) => setAttributes( { showLabel: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showLabel && <div className="yaycheckout-field-value__label">{ fieldLabel }</div> }
				{ /* The muted text the shopper sees until they fill the field; empty falls back to the field's name. */ }
				<RichText
					tagName="div"
					className="yaycheckout-field-value__value is-empty"
					value={ placeholder }
					onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					placeholder={ fieldLabel }
					allowedFormats={ [] }
					withoutInteractiveFormatting
				/>
			</div>
		</>
	);
}
