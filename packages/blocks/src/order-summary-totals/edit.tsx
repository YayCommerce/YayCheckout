import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<table className="yaycheckout-order-summary__table">
				<tbody>
					<tr>
						<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--muted">{ __( 'Subtotal', 'yaycheckout' ) }</td>
						<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--end yaycheckout-order-summary__cell--muted">$20.00</td>
					</tr>
					<tr>
						<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--total">{ __( 'Total', 'yaycheckout' ) }</td>
						<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--end yaycheckout-order-summary__cell--total">$20.00</td>
					</tr>
				</tbody>
			</table>
		</div>
	);
}
