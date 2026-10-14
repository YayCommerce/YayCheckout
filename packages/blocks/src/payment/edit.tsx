import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { layoutFor, PAYMENT_LAYOUTS } from './layouts';
import { StylePanels } from './style-panels';
import { paymentStyleVars } from './style-paths';
import {
	SectionDescription,
	SectionHeadingToolbar,
	sectionTextCSS,
	SectionTextPanels,
	SectionTitle,
} from '../shared/section-heading';
import '../shared/section-preview.css';

interface PaymentAttributes {
	label: string;
	showTitle?: boolean;
	headingLevel?: number;
	description?: string;
	layout?: string;
	style?: Record< string, unknown >;
	textColor?: string;
}

interface EditProps {
	clientId: string;
	attributes: PaymentAttributes;
	setAttributes: ( attributes: Partial< PaymentAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { label, headingLevel, layout, style, textColor } = attributes;
	const showTitle = attributes.showTitle !== false;
	const description = attributes.description ?? '';
	const selectedLayout = layoutFor( layout );
	// Preview only: which row looks selected. Never stored.
	const [ selectedRow, setSelectedRow ] = useState( 0 );
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
	const textCSS = sectionTextCSS( clientId, style );

	return (
		<>
			{ showTitle && (
				<SectionHeadingToolbar level={ headingLevel } onChange={ ( level ) => setAttributes( { headingLevel: level } ) } />
			) }
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show title', 'yaycheckout' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
					{ showTitle && (
						<TextControl
							label={ __( 'Label', 'yaycheckout' ) }
							value={ label }
							onChange={ ( value ) => setAttributes( { label: value } ) }
						/>
					) }
					<TextControl
						label={ __( 'Description', 'yaycheckout' ) }
						value={ description }
						onChange={ ( value ) => setAttributes( { description: value } ) }
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
				<SectionTextPanels
					style={ style }
					onChange={ ( next ) => setAttributes( { style: next } ) }
					showTitle={ showTitle }
					showDescription={ description.trim() !== '' }
				/>
				<StylePanels
					keys={ selectedLayout.settings }
					style={ style }
					onChange={ ( next ) => setAttributes( { style: next } ) }
				/>
			</InspectorControls>
			<div { ...blockProps } style={ wrapperStyle }>
				{ textCSS && <style>{ textCSS }</style> }
				{ showTitle && (
					<SectionTitle level={ headingLevel } text={ heading } className="yaycheckout-section-preview__heading" />
				) }
				<SectionDescription text={ description } />
				{ /* WooCommerce's list markup, so the block's own CSS draws it as on the storefront. */ }
				<div id="payment" className="woocommerce-checkout-payment">
					<ul className="wc_payment_methods payment_methods methods">
						{ [ __( 'Direct bank transfer', 'yaycheckout' ), __( 'Cash on delivery', 'yaycheckout' ) ].map( ( name, index ) => (
							<li key={ name } className="wc_payment_method" onClick={ () => setSelectedRow( index ) }>
								<input type="radio" className="input-radio" disabled readOnly checked={ selectedRow === index } />
								<label>{ name }</label>
							</li>
						) ) }
					</ul>
				</div>
			</div>
		</>
	);
}
