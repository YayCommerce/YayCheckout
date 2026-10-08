import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../shared/section-preview.css';

interface PaymentAttributes {
	label: string;
}

interface EditProps {
	attributes: PaymentAttributes;
	setAttributes: ( attributes: Partial< PaymentAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-payment' } );
	const heading = label || __( 'Payment', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Payment', 'yaycheckout' ) }>
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
				<div id="payment" className="woocommerce-checkout-payment">
					<ul className="wc_payment_methods payment_methods methods">
						<li className="wc_payment_method">
							<input type="radio" disabled defaultChecked />
							<label>{ __( 'Direct bank transfer', 'yaycheckout' ) }</label>
						</li>
						<li className="wc_payment_method">
							<input type="radio" disabled />
							<label>{ __( 'Cash on delivery', 'yaycheckout' ) }</label>
						</li>
					</ul>
				</div>
			</div>
		</>
	);
}
