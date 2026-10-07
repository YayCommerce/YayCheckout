import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { FieldPreview } from '../shared/field-preview';
import './editor.css';

interface CreateAccountAttributes {
	checkboxLabel: string;
}

interface EditProps {
	attributes: CreateAccountAttributes;
	setAttributes: ( attributes: Partial< CreateAccountAttributes > ) => void;
}

/**
 * Static editor preview of the account fields. The storefront follows
 * WooCommerce's Accounts & Privacy settings; see render.php.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { checkboxLabel } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-create-account' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Create account', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Checkbox label', 'yaycheckout' ) }
						value={ decodeEntities( checkboxLabel ) }
						onChange={ ( value ) => setAttributes( { checkboxLabel: value } ) }
					/>
					<Notice status="info" isDismissible={ false }>
						{ __(
							"Shown only to guests, and only when WooCommerce > Settings > Accounts & Privacy allows account creation during checkout. The checkbox appears only while guest checkout is on. Username and password appear only when WooCommerce doesn't generate them.",
							'yaycheckout'
						) }
					</Notice>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="yaycheckout-create-account__checkbox">
					<input type="checkbox" disabled />
					{ /* A span, not a label: typing Space would toggle a label's checkbox. */ }
					<RichText
						tagName="span"
						value={ checkboxLabel }
						onChange={ ( value: string ) => setAttributes( { checkboxLabel: value } ) }
						placeholder={ __( 'Create an account?', 'yaycheckout' ) }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				</div>
				<FieldPreview
					label={ __( 'Create account password', 'yaycheckout' ) }
					type="password"
					required
				/>
			</div>
		</>
	);
}
