import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

/**
 * Static preview of WooCommerce's order customer details. Titles come from WooCommerce's
 * template; the storefront prints order/order-details-customer.php.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-order-customer-details' } );

	return (
		<div { ...blockProps }>
			<section className="yaycheckout-order-customer-details__preview woocommerce-customer-details">
				<div className="yaycheckout-order-customer-details__columns">
					<div>
						<h2 className="woocommerce-column__title">{ __( 'Billing address', 'yaycheckout' ) }</h2>
						<address>{ __( 'Sample billing address', 'yaycheckout' ) }</address>
					</div>
					<div>
						<h2 className="woocommerce-column__title">{ __( 'Shipping address', 'yaycheckout' ) }</h2>
						<address>{ __( 'Sample shipping address', 'yaycheckout' ) }</address>
					</div>
				</div>
			</section>
		</div>
	);
}
