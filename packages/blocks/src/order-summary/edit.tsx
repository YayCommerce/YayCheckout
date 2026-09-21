import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface OrderSummaryAttributes {
	label: string;
}

interface EditProps {
	attributes: OrderSummaryAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryAttributes > ) => void;
}

const cellStyle = { padding: '8px 0', borderBottom: '1px solid #ddd', textAlign: 'left' as const };
const muted = { color: '#646970' };

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
				<h3 style={ { margin: '0 0 8px', fontSize: '16px' } }>{ heading }</h3>
				<table style={ { width: '100%', borderCollapse: 'collapse', fontSize: '13px' } }>
					<thead>
						<tr>
							<th style={ cellStyle }>{ __( 'Product', 'yaycheckout' ) }</th>
							<th style={ { ...cellStyle, textAlign: 'right' } }>{ __( 'Subtotal', 'yaycheckout' ) }</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td style={ { ...cellStyle, ...muted } }>{ __( 'Sample product × 1', 'yaycheckout' ) }</td>
							<td style={ { ...cellStyle, textAlign: 'right', ...muted } }>$20.00</td>
						</tr>
						<tr>
							<td style={ { padding: '8px 0', fontWeight: 600 } }>{ __( 'Total', 'yaycheckout' ) }</td>
							<td style={ { padding: '8px 0', textAlign: 'right', fontWeight: 600 } }>$20.00</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}
