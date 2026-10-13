import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';

interface OrderSummaryBreakdownAttributes {
	showSubtotal: boolean;
	subtotalLabel?: string;
}

interface EditProps {
	attributes: OrderSummaryBreakdownAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryBreakdownAttributes > ) => void;
}

// Mirrors CheckoutFragments::order_breakdown_html(), so its style.css draws it as on the storefront.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { showSubtotal, subtotalLabel } = attributes;
	const subtotalPlaceholder = __( 'Subtotal', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order summary breakdown', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show subtotal', 'yaycheckout' ) }
						checked={ showSubtotal }
						onChange={ ( value ) => setAttributes( { showSubtotal: value } ) }
					/>
					{ showSubtotal && (
						<TextControl
							label={ __( 'Subtotal label', 'yaycheckout' ) }
							value={ subtotalLabel ?? '' }
							placeholder={ subtotalPlaceholder }
							onChange={ ( value ) => setAttributes( { subtotalLabel: value } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<table className="shop_table yaycheckout-order-summary-breakdown">
					<tbody>
						{ showSubtotal && (
							<tr className="cart-subtotal">
								<th>{ plainText( subtotalLabel ) || subtotalPlaceholder }</th>
								<td>$20.00</td>
							</tr>
						) }
						<tr className="yaycheckout-order-summary-breakdown__shipping">
							<th>{ __( 'Free shipping', 'yaycheckout' ) }</th>
							<td>
								<strong>{ __( 'Free', 'yaycheckout' ) }</strong>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}
