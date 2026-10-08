import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<table className="yaycheckout-order-summary__table">
				<thead>
					<tr>
						<th className="yaycheckout-order-summary__cell">{ __( 'Product', 'yaycheckout' ) }</th>
						<th className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--end">{ __( 'Subtotal', 'yaycheckout' ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--muted">{ __( 'Sample product × 1', 'yaycheckout' ) }</td>
						<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--end yaycheckout-order-summary__cell--muted">$20.00</td>
					</tr>
				</tbody>
			</table>
		</div>
	);
}
