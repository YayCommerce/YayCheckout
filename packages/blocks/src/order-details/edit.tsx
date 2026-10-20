import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface OrderDetailsAttributes {
	title: string;
	productLabel: string;
	totalLabel: string;
}

interface EditProps {
	attributes: OrderDetailsAttributes;
	setAttributes: ( attributes: Partial< OrderDetailsAttributes > ) => void;
}

/**
 * Preview of WooCommerce's order details table. Heading and column labels are RichText; empty
 * attributes fall back to WooCommerce's strings on the storefront.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { title, productLabel, totalLabel } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-order-details' } );

	return (
		<div { ...blockProps }>
			<section className="yaycheckout-order-details__preview woocommerce-order-details">
				<RichText
					tagName="h2"
					className="woocommerce-order-details__title"
					value={ title }
					onChange={ ( value: string ) => setAttributes( { title: value } ) }
					placeholder={ __( 'Order details', 'yaycheckout' ) }
					allowedFormats={ [] }
					withoutInteractiveFormatting
				/>
				<table className="woocommerce-table shop_table order_details">
					<thead>
						<tr>
							<th className="product-name">
								<RichText
									tagName="span"
									value={ productLabel }
									onChange={ ( value: string ) => setAttributes( { productLabel: value } ) }
									placeholder={ __( 'Product', 'yaycheckout' ) }
									allowedFormats={ [] }
									withoutInteractiveFormatting
								/>
							</th>
							<th className="product-total">
								<RichText
									tagName="span"
									value={ totalLabel }
									onChange={ ( value: string ) => setAttributes( { totalLabel: value } ) }
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
