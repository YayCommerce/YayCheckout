import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { layoutFor, SHIPPING_LAYOUTS } from './layouts';
import { StylePanels } from './style-panels';
import { shippingStyleVars } from './style-paths';
import '../shared/section-preview.css';

interface ShippingMethodsAttributes {
	label: string;
	layout?: string;
	style?: Record< string, unknown >;
	textColor?: string;
}

interface EditProps {
	attributes: ShippingMethodsAttributes;
	setAttributes: ( attributes: Partial< ShippingMethodsAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, layout, style, textColor } = attributes;
	const selectedLayout = layoutFor( layout );
	const blockProps = useBlockProps( {
		className: 'yaycheckout-shipping-methods',
		'data-layout': selectedLayout.value,
		style: shippingStyleVars( style, textColor ),
	} );
	const heading = label || __( 'Shipping methods', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Shipping methods', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<SelectControl
						label={ __( 'Layout', 'yaycheckout' ) }
						value={ selectedLayout.value }
						options={ SHIPPING_LAYOUTS.map( ( option ) => ( {
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
			<div { ...blockProps }>
				<h3 className="yaycheckout-section-preview__heading">{ heading }</h3>
				{ /* Same row markup the storefront prints, so the block CSS draws it the same way. */ }
				<ul id="shipping_method" className="woocommerce-shipping-methods yaycheckout-shipping-methods__list">
					<li className="yaycheckout-shipping-methods__option">
						<input type="radio" className="shipping_method" disabled defaultChecked />
						<label>
							<span className="yaycheckout-shipping-methods__indicator" aria-hidden="true" />
							<span className="yaycheckout-shipping-methods__body">
								<span className="yaycheckout-shipping-methods__name">{ __( 'Flat rate', 'yaycheckout' ) }</span>
							</span>
							<span className="yaycheckout-shipping-methods__price">{ __( '$5.00', 'yaycheckout' ) }</span>
						</label>
					</li>
					<li className="yaycheckout-shipping-methods__option">
						<input type="radio" className="shipping_method" disabled />
						<label>
							<span className="yaycheckout-shipping-methods__indicator" aria-hidden="true" />
							<span className="yaycheckout-shipping-methods__body">
								<span className="yaycheckout-shipping-methods__name">{ __( 'Free shipping', 'yaycheckout' ) }</span>
							</span>
						</label>
					</li>
				</ul>
			</div>
		</>
	);
}
