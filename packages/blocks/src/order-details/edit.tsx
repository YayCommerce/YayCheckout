import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

/**
 * Static preview of WooCommerce's order details table (items and totals only). Addresses are a
 * separate Order Addresses block. Titles come from WooCommerce's template and are not editable here.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-order-details' } );

	return (
		<div { ...blockProps }>
			<section className="yaycheckout-order-details__preview woocommerce-order-details">
				<h2 className="woocommerce-order-details__title">{ __( 'Order details', 'yaycheckout' ) }</h2>
				<table className="woocommerce-table shop_table order_details">
					<thead>
						<tr>
							<th className="product-name">{ __( 'Product', 'yaycheckout' ) }</th>
							<th className="product-total">{ __( 'Total', 'yaycheckout' ) }</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								{ __( 'Sample product', 'yaycheckout' ) }&nbsp;
								<strong className="product-quantity">&times;&nbsp;1</strong>
							</td>
							<td>
								<span className="woocommerce-Price-amount amount">{ __( '$0.00', 'yaycheckout' ) }</span>
							</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th scope="row">{ __( 'Total', 'yaycheckout' ) }</th>
							<td>
								<span className="woocommerce-Price-amount amount">{ __( '$0.00', 'yaycheckout' ) }</span>
							</td>
						</tr>
					</tfoot>
				</table>
			</section>
		</div>
	);
}
