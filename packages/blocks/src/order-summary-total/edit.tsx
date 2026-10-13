import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';

interface OrderSummaryTotalAttributes {
	label?: string;
	showLabel: boolean;
	showCurrencyCode: boolean;
}

interface EditProps {
	attributes: OrderSummaryTotalAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryTotalAttributes > ) => void;
}

// Mirrors CheckoutFragments::order_total_html(), so its style.css draws it as on the storefront.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, showLabel, showCurrencyCode } = attributes;
	const placeholder = __( 'Total', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order summary total', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show label', 'yaycheckout' ) }
						checked={ showLabel }
						onChange={ ( value ) => setAttributes( { showLabel: value } ) }
					/>
					{ showLabel && (
						<TextControl
							label={ __( 'Total label', 'yaycheckout' ) }
							value={ label ?? '' }
							placeholder={ placeholder }
							onChange={ ( value ) => setAttributes( { label: value } ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Show currency code', 'yaycheckout' ) }
						checked={ showCurrencyCode }
						onChange={ ( value ) => setAttributes( { showCurrencyCode: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<table className="shop_table yaycheckout-order-summary-total">
					<tbody>
						<tr className="order-total">
							{ showLabel && <th>{ plainText( label ) || placeholder }</th> }
							<td>
								{ showCurrencyCode && (
									<span className="yaycheckout-order-summary-total__currency">USD</span>
								) }
								$20.00
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}
