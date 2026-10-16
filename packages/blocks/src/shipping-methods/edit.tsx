import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { shippingSetupData } from './editor-data';
import { layoutFor, SHIPPING_LAYOUTS } from './layouts';
import { StylePanels } from './style-panels';
import { shippingStyleVars } from './style-paths';
import {
	SectionDescription,
	SectionHeadingToolbar,
	sectionTextCSS,
	SectionTextPanels,
	SectionTitle,
} from '../shared/section-heading';
import { SetupNotice } from '../shared/setup-notice';
import '../shared/section-preview.css';

interface ShippingMethodsAttributes {
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
	attributes: ShippingMethodsAttributes;
	setAttributes: ( attributes: Partial< ShippingMethodsAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { label, headingLevel, layout, style, textColor } = attributes;
	const showTitle = attributes.showTitle !== false;
	const description = attributes.description ?? '';
	const selectedLayout = layoutFor( layout );
	// Preview only: which row looks selected. Never stored.
	const [ selectedRow, setSelectedRow ] = useState( 0 );
	const blockProps = useBlockProps( {
		className: 'yaycheckout-shipping-methods',
		'data-layout': selectedLayout.value,
		style: shippingStyleVars( style, textColor ),
	} );
	const heading = label || __( 'Shipping methods', 'yaycheckout' );
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
							label={ __( 'Title', 'yaycheckout' ) }
							value={ label }
							onChange={ ( value ) => setAttributes( { label: value } ) }
						/>
					) }
					<TextControl
						label={ __( 'Description', 'yaycheckout' ) }
						value={ description }
						onChange={ ( value ) => setAttributes( { description: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Methods', 'yaycheckout' ) }>
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
			<div { ...blockProps }>
				<SetupNotice
					data={ shippingSetupData }
					message={ __(
						'No shipping zone or method is set up, so shoppers check out without choosing shipping and do not see this block.',
						'yaycheckout'
					) }
					linkText={ __( 'Shipping settings', 'yaycheckout' ) }
				/>
				{ textCSS && <style>{ textCSS }</style> }
				{ showTitle && (
					<SectionTitle level={ headingLevel } text={ heading } className="yaycheckout-section-preview__heading" />
				) }
				<SectionDescription text={ description } />
				{ /* Same row markup the storefront prints, so the block CSS draws it the same way. */ }
				<ul id="shipping_method" className="woocommerce-shipping-methods yaycheckout-shipping-methods__list">
					{ [
						{ name: __( 'Flat rate', 'yaycheckout' ), price: __( '$5.00', 'yaycheckout' ) },
						{ name: __( 'Free shipping', 'yaycheckout' ), price: '' },
					].map( ( row, index ) => (
						<li key={ row.name } className="yaycheckout-shipping-methods__option" onClick={ () => setSelectedRow( index ) }>
							<input type="radio" className="shipping_method" disabled readOnly checked={ selectedRow === index } />
							<label>
								<span className="yaycheckout-shipping-methods__indicator" aria-hidden="true" />
								<span className="yaycheckout-shipping-methods__body">
									<span className="yaycheckout-shipping-methods__name">{ row.name }</span>
								</span>
								{ row.price && <span className="yaycheckout-shipping-methods__price">{ row.price }</span> }
							</label>
						</li>
					) ) }
				</ul>
			</div>
		</>
	);
}
