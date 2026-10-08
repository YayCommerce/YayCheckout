import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

// Mirrors CheckoutFragments::order_items_html(), so checkout-base.css draws it as on the storefront.
export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<table className="shop_table yaycheckout-order-summary-items">
				<thead>
					<tr>
						<th className="product-name">{ __( 'Product', 'yaycheckout' ) }</th>
						<th className="product-total">{ __( 'Subtotal', 'yaycheckout' ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr className="cart_item">
						<td className="product-name">
							{ __( 'Sample product', 'yaycheckout' ) }&nbsp;<strong className="product-quantity">×&nbsp;1</strong>
						</td>
						<td className="product-total">$20.00</td>
					</tr>
				</tbody>
			</table>
		</div>
	);
}
