import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.css';

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
				<h3 className="yaycheckout-shipping-methods__heading">{ heading }</h3>
				<label className="yaycheckout-shipping-methods__option">
					<input type="radio" disabled defaultChecked />{ ' ' }
					{ __( 'Flat rate — $5.00', 'yaycheckout' ) }
				</label>
				<label className="yaycheckout-shipping-methods__option">
					<input type="radio" disabled />{ ' ' }
					{ __( 'Free shipping — $0.00', 'yaycheckout' ) }
				</label>
			</div>
		</>
	);
}
