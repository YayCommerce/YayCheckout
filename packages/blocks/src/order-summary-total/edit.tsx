import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plainText } from '../shared/plain-text';
import { SummaryPartPanels, summaryPartCSS } from '../shared/summary-part-styles';

interface OrderSummaryTotalAttributes {
	label?: string;
	showLabel: boolean;
	showCurrencyCode: boolean;
	style?: Record< string, unknown >;
}

interface EditProps {
	clientId: string;
	attributes: OrderSummaryTotalAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryTotalAttributes > ) => void;
}

// Mirrors CheckoutFragments::order_total_html(), so its style.css draws it as on the storefront.
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { label, showLabel, showCurrencyCode, style } = attributes;
	const placeholder = __( 'Total', 'yaycheckout' );
	const css = summaryPartCSS( clientId, 'yaycheckout-order-summary-total', style );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
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
			<InspectorControls group="styles">
				<SummaryPartPanels
					style={ style }
					onChange={ ( next ) => setAttributes( { style: next } ) }
					showLabel={ showLabel }
				/>
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ css && <style>{ css }</style> }
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
