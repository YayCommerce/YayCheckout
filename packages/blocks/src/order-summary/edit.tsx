import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../shared/section-preview.css';
import './editor.css';

interface OrderSummaryAttributes {
	label: string;
}

interface EditProps {
	attributes: OrderSummaryAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-order-summary' } );
	const heading = label || __( 'Your order', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order summary', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3 className="yaycheckout-section-preview__heading">{ heading }</h3>
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
						<tr>
							<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--total">{ __( 'Total', 'yaycheckout' ) }</td>
							<td className="yaycheckout-order-summary__cell yaycheckout-order-summary__cell--end yaycheckout-order-summary__cell--total">$20.00</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}
