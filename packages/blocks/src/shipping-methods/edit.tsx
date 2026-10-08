import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../shared/section-preview.css';

interface ShippingMethodsAttributes {
	label: string;
}

interface EditProps {
	attributes: ShippingMethodsAttributes;
	setAttributes: ( attributes: Partial< ShippingMethodsAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-shipping-methods' } );
	const heading = label || __( 'Shipping methods', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Shipping methods', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3 className="yaycheckout-section-preview__heading">{ heading }</h3>
				{ /* WooCommerce's list markup, so the block's choice-row CSS draws it as on the storefront. */ }
				<ul id="shipping_method" className="woocommerce-shipping-methods">
					<li>
						<input type="radio" disabled defaultChecked />
						<label>{ __( 'Flat rate — $5.00', 'yaycheckout' ) }</label>
					</li>
					<li>
						<input type="radio" disabled />
						<label>{ __( 'Free shipping — $0.00', 'yaycheckout' ) }</label>
					</li>
				</ul>
			</div>
		</>
	);
}
