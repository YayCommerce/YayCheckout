import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { layoutFor, PAYMENT_LAYOUTS } from './layouts';
import { StylePanels } from './style-panels';
import { paymentStyleVars } from './style-paths';
import '../shared/section-preview.css';

interface PaymentAttributes {
	label: string;
	layout?: string;
	style?: Record< string, unknown >;
	textColor?: string;
}

interface EditProps {
	attributes: PaymentAttributes;
	setAttributes: ( attributes: Partial< PaymentAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, layout, style, textColor } = attributes;
	const selectedLayout = layoutFor( layout );
	// Same as Step: useBlockProps owns Dimensions. Methods vars are merged after.
	const blockProps = useBlockProps( {
		className: 'yaycheckout-payment',
		'data-layout': selectedLayout.value,
	} );
	const wrapperStyle = {
		...blockProps.style,
		...paymentStyleVars( style, textColor ),
	};
	const heading = label || __( 'Payment', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Payment', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<SelectControl
						label={ __( 'Layout', 'yaycheckout' ) }
						value={ selectedLayout.value }
						options={ PAYMENT_LAYOUTS.map( ( option ) => ( {
							label: option.label,
							value: option.value,
						} ) ) }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<StylePanels
					keys={ selectedLayout.settings }
					style={ style }
					onChange={ ( next ) => setAttributes( { style: next } ) }
				/>
			</InspectorControls>
			<div { ...blockProps } style={ wrapperStyle }>
				<h3 className="yaycheckout-section-preview__heading">{ heading }</h3>
				{ /* WooCommerce's list markup, so the block's own CSS draws it as on the storefront. */ }
				<div id="payment" className="woocommerce-checkout-payment">
					<ul className="wc_payment_methods payment_methods methods">
						<li className="wc_payment_method">
							<input type="radio" className="input-radio" disabled defaultChecked />
							<label>{ __( 'Direct bank transfer', 'yaycheckout' ) }</label>
						</li>
						<li className="wc_payment_method">
							<input type="radio" className="input-radio" disabled />
							<label>{ __( 'Cash on delivery', 'yaycheckout' ) }</label>
						</li>
					</ul>
				</div>
			</div>
		</>
	);
}
