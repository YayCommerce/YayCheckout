import { InspectorControls, PanelColorSettings, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { layoutFor, PAYMENT_LAYOUTS } from './layouts';
import {
	BACKGROUND_SETTING_LABELS,
	isBackgroundSetting,
	SETTING_CONTROLS,
	type PaymentSettingKey,
} from './setting-controls';
import { getStyleSetting, setStyleSetting, paymentStyleVars } from './style-paths';
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

function colorValue( value: unknown ): string | undefined {
	return typeof value === 'string' && value !== '' ? value : undefined;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label, layout, style, textColor } = attributes;
	// Same as Step: useBlockProps owns Dimensions. Methods vars are merged after.
	const blockProps = useBlockProps( {
		className: 'yaycheckout-payment',
		'data-layout': layout || 'default',
	} );
	const wrapperStyle = {
		...blockProps.style,
		...paymentStyleVars( style, textColor ),
	};
	const heading = label || __( 'Payment', 'yaycheckout' );
	const selectedLayout = layoutFor( layout );
	const rangeKeys = selectedLayout.settings.filter(
		( key ): key is PaymentSettingKey => ! isBackgroundSetting( key ) && Boolean( SETTING_CONTROLS[ key ] )
	);
	const colorKeys = selectedLayout.settings.filter( isBackgroundSetting );
	const showMethods = rangeKeys.length > 0 || colorKeys.length > 0;

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
				{ showMethods && (
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
						{ colorKeys.length > 0 && (
							<PanelColorSettings
								title={ __( 'Background', 'yaycheckout' ) }
								showTitle={ false }
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
					</PanelBody>
				) }
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
