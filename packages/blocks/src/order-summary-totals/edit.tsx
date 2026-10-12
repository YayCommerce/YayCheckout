import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';

interface OrderSummaryTotalsAttributes {
	showCurrencyCode: boolean;
	showSubtotal: boolean;
	showTotal: boolean;
	subtotalLabel?: string;
	totalLabel?: string;
}

interface EditProps {
	attributes: OrderSummaryTotalsAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryTotalsAttributes > ) => void;
}

// Mirrors CheckoutFragments::order_totals_html(), so checkout-base.css draws it as on the storefront.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { showCurrencyCode, showSubtotal, showTotal, subtotalLabel, totalLabel } = attributes;
	const subtotalPlaceholder = __( 'Subtotal', 'yaycheckout' );
	const totalPlaceholder = __( 'Total', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order summary totals', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show currency code', 'yaycheckout' ) }
						checked={ showCurrencyCode }
						onChange={ ( value ) => setAttributes( { showCurrencyCode: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show total', 'yaycheckout' ) }
						checked={ showTotal }
						onChange={ ( value ) => setAttributes( { showTotal: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show subtotal', 'yaycheckout' ) }
						checked={ showSubtotal }
						onChange={ ( value ) => setAttributes( { showSubtotal: value } ) }
					/>
					{ showTotal && (
						<TextControl
							label={ __( 'Total label', 'yaycheckout' ) }
							value={ totalLabel ?? '' }
							placeholder={ totalPlaceholder }
							onChange={ ( value ) => setAttributes( { totalLabel: value } ) }
						/>
					) }
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
				<table className="shop_table yaycheckout-order-summary-totals">
					<tbody>
						{ showSubtotal && (
							<tr className="cart-subtotal">
								<th>{ plainText( subtotalLabel ) || subtotalPlaceholder }</th>
								<td>$20.00</td>
							</tr>
						) }
						<tr className="yaycheckout-order-summary-totals__shipping">
							<th>
								{ __( 'Delivery', 'yaycheckout' ) }
								<span className="yaycheckout-order-summary-totals__via">
									{ __( 'Free shipping', 'yaycheckout' ) }
								</span>
							</th>
							<td>
								<strong>{ __( 'Free', 'yaycheckout' ) }</strong>
							</td>
						</tr>
						{ showTotal && (
							<tr className="order-total">
								<th>{ plainText( totalLabel ) || totalPlaceholder }</th>
								<td>
									{ showCurrencyCode && (
										<span className="yaycheckout-order-summary-totals__currency">USD</span>
									) }
									$20.00
								</td>
							</tr>
						) }
					</tbody>
				</table>
			</div>
		</>
	);
}
