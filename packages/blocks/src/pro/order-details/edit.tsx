import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface OrderDetailsAttributes {
	showTitle?: boolean;
	title: string;
	productLabel: string;
	totalLabel: string;
	subtotalLabel: string;
	shippingLabel: string;
	paymentMethodLabel: string;
	orderTotalLabel: string;
}

interface EditProps {
	attributes: OrderDetailsAttributes;
	setAttributes: ( attributes: Partial< OrderDetailsAttributes > ) => void;
}

/**
 * Preview of WooCommerce's order details table. Heading, column and totals-row labels are
 * RichText; empty attributes fall back to WooCommerce's strings on the storefront. Show title
 * hides the WC heading when off.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const {
		title,
		productLabel,
		totalLabel,
		subtotalLabel,
		shippingLabel,
		paymentMethodLabel,
		orderTotalLabel,
	} = attributes;
	const showTitle = attributes.showTitle !== false;
	const blockProps = useBlockProps( { className: 'yaycheckout-order-details' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show title', 'yaycheckout' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<section className="yaycheckout-order-details__preview woocommerce-order-details">
					{ showTitle && (
						<RichText
							tagName="h2"
							className="woocommerce-order-details__title"
							value={ title }
							onChange={ ( value: string ) => setAttributes( { title: value } ) }
							placeholder={ __( 'Order details', 'yaycheckout' ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
					) }
					<table className="woocommerce-table shop_table order_details">
						<thead>
							<tr>
								<th className="product-name">
									<RichText
										tagName="span"
										value={ productLabel }
										onChange={ ( value: string ) =>
											setAttributes( { productLabel: value } )
										}
										placeholder={ __( 'Product', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
								<th className="product-total">
									<RichText
										tagName="span"
										value={ totalLabel }
										onChange={ ( value: string ) =>
											setAttributes( { totalLabel: value } )
										}
										placeholder={ __( 'Total', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									{ __( 'Sample product', 'yaycheckout' ) }&nbsp;
									<strong className="product-quantity">&times;&nbsp;1</strong>
								</td>
								<td>
									<span className="woocommerce-Price-amount amount">
										{ __( '$0.00', 'yaycheckout' ) }
									</span>
								</td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<th scope="row">
									<RichText
										tagName="span"
										value={ subtotalLabel }
										onChange={ ( value: string ) =>
											setAttributes( { subtotalLabel: value } )
										}
										placeholder={ __( 'Subtotal:', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
								<td>
									<span className="woocommerce-Price-amount amount">
										{ __( '$0.00', 'yaycheckout' ) }
									</span>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<RichText
										tagName="span"
										value={ shippingLabel }
										onChange={ ( value: string ) =>
											setAttributes( { shippingLabel: value } )
										}
										placeholder={ __( 'Shipping:', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
								<td>{ __( 'Free', 'yaycheckout' ) }</td>
							</tr>
							<tr>
								<th scope="row">
									<RichText
										tagName="span"
										value={ paymentMethodLabel }
										onChange={ ( value: string ) =>
											setAttributes( { paymentMethodLabel: value } )
										}
										placeholder={ __( 'Payment method:', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
								<td>{ __( 'Direct bank transfer', 'yaycheckout' ) }</td>
							</tr>
							<tr>
								<th scope="row">
									<RichText
										tagName="span"
										value={ orderTotalLabel }
										onChange={ ( value: string ) =>
											setAttributes( { orderTotalLabel: value } )
										}
										placeholder={ __( 'Total:', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
								<td>
									<span className="woocommerce-Price-amount amount">
										{ __( '$0.00', 'yaycheckout' ) }
									</span>
								</td>
							</tr>
						</tfoot>
					</table>
				</section>
			</div>
		</>
	);
}
