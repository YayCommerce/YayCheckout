import { InspectorControls, PanelColorSettings, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { layoutFor, SHIPPING_LAYOUTS } from './layouts';
import {
	BACKGROUND_SETTING_LABELS,
	isBackgroundSetting,
	SETTING_CONTROLS,
	type ShippingSettingKey,
} from './setting-controls';
import { getStyleSetting, setStyleSetting, shippingStyleVars } from './style-paths';
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

function colorValue( value: unknown ): string | undefined {
	return typeof value === 'string' && value !== '' ? value : undefined;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, layout, style, textColor } = attributes;
	const blockProps = useBlockProps( {
		className: 'yaycheckout-shipping-methods',
		'data-layout': layout || 'default',
		style: shippingStyleVars( style, textColor ),
	} );
	const heading = label || __( 'Shipping methods', 'yaycheckout' );
	const selectedLayout = layoutFor( layout );
	const rangeKeys = selectedLayout.settings.filter(
		( key ): key is ShippingSettingKey => ! isBackgroundSetting( key ) && Boolean( SETTING_CONTROLS[ key ] )
	);
	const colorKeys = selectedLayout.settings.filter( isBackgroundSetting );

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
				{ rangeKeys.length > 0 && (
					<PanelBody title={ __( 'Methods', 'yaycheckout' ) }>
						{ rangeKeys.map( ( key ) => {
							const Control = SETTING_CONTROLS[ key ];
							if ( ! Control ) {
								return null;
							}

							return (
								<Control
									key={ key }
									value={ getStyleSetting( style, key ) }
									onChange={ ( value ) =>
										setAttributes( {
											style: setStyleSetting( style, key, value ),
										} )
									}
								/>
							);
						} ) }
					</PanelBody>
				) }
				{ colorKeys.length > 0 && (
					<PanelColorSettings
						title={ __( 'Background', 'yaycheckout' ) }
						__experimentalIsRenderedInSidebar
						colorSettings={ colorKeys.map( ( key ) => ( {
							label: BACKGROUND_SETTING_LABELS[ key ],
							value: colorValue( getStyleSetting( style, key ) ),
							onChange: ( value: string | undefined ) =>
								setAttributes( {
									style: setStyleSetting( style, key, value || undefined ),
								} ),
						} ) ) }
					/>
				) }
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
