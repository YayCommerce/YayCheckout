import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SectionLabel } from '../shared/section-label';

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

	return (
		<div { ...blockProps }>
			<SectionLabel
				panelTitle={ __( 'Payment', 'yaycheckout' ) }
				label={ label }
				defaultLabel={ __( 'Payment', 'yaycheckout' ) }
				setAttributes={ setAttributes }
			/>
			{ /* WooCommerce's list markup, so the block's own CSS draws it as on the storefront. */ }
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
	);
}
