import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SectionLabel } from '../shared/section-label';

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

	return (
		<div { ...blockProps }>
			<SectionLabel
				panelTitle={ __( 'Shipping methods', 'yaycheckout' ) }
				label={ label }
				defaultLabel={ __( 'Shipping methods', 'yaycheckout' ) }
				setAttributes={ setAttributes }
			/>
			{ /* WooCommerce's list markup, so the block's own CSS draws it as on the storefront. */ }
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
	);
}
