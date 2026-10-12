import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

// Mirrors CheckoutFragments::order_totals_html(), so checkout-base.css draws it as on the storefront.
export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<table className="shop_table yaycheckout-order-summary-totals">
				<tbody>
					<tr className="cart-subtotal">
						<th>{ __( 'Subtotal', 'yaycheckout' ) }</th>
						<td>$20.00</td>
					</tr>
					<tr className="yaycheckout-order-summary-totals__shipping">
						<th>{ __( 'Free shipping', 'yaycheckout' ) }</th>
						<td>
							<strong>{ __( 'Free', 'yaycheckout' ) }</strong>
						</td>
					</tr>
					<tr className="order-total">
						<th>{ __( 'Total', 'yaycheckout' ) }</th>
						<td>$20.00</td>
					</tr>
				</tbody>
			</table>
		</div>
	);
}
