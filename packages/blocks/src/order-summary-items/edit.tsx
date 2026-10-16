import {
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { plainText } from '../shared/plain-text';
import '../shared/tools-panel.css';
import './editor.css';

interface OrderSummaryItemsAttributes {
	showLabels: boolean;
	productLabel?: string;
	subtotalLabel?: string;
	showThumbnails: boolean;
	itemData: 'list' | 'inline' | 'hidden';
	showSku: boolean;
	showUnitPrice: boolean;
	showShortDescription: boolean;
	quantityDisplay: 'inline' | 'stepper' | 'hidden';
	showBadge: boolean;
	badgeBackgroundColor: string;
	badgeTextColor: string;
	badgeSize?: number;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryItemsAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryItemsAttributes > ) => void;
	clientId: string;
}

const BADGE_PANEL = 'yaycheckout-order-summary-items-badge';

// Mirrors CheckoutFragments::order_items_badge_style(): the badge settings as --yaycheckout-badge-* vars.
function badgeStyle( { badgeBackgroundColor, badgeTextColor, badgeSize }: OrderSummaryItemsAttributes ) {
	return {
		...( badgeBackgroundColor ? { '--yaycheckout-badge-bg': badgeBackgroundColor } : {} ),
		...( badgeTextColor ? { '--yaycheckout-badge-text': badgeTextColor } : {} ),
		...( typeof badgeSize === 'number'
			? { '--yaycheckout-badge-size': `${ Math.max( 16, Math.min( 32, badgeSize ) ) }px` }
			: {} ),
	};
}

function quantityHelp( quantityDisplay: OrderSummaryItemsAttributes[ 'quantityDisplay' ], badge: boolean ) {
	if ( quantityDisplay === 'stepper' ) {
		return __( 'Lets shoppers change or remove items.', 'yaycheckout' );
	}

	return quantityDisplay === 'hidden' && ! badge ? __( "Shoppers won't see item quantities.", 'yaycheckout' ) : undefined;
}

// Mirrors CheckoutFragments::order_items_html(), so the block's style.css draws it as on the storefront.
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const {
		showLabels,
		productLabel,
		subtotalLabel,
		showThumbnails,
		itemData,
		showSku,
		showUnitPrice,
		showShortDescription,
		quantityDisplay,
		showBadge,
		badgeBackgroundColor,
		badgeTextColor,
		badgeSize,
		style,
	} = attributes;
	// As order_items_html(): a badge needs a thumbnail to sit on, and adds to Show as.
	const badge = showBadge && showThumbnails;
	const stepper = quantityDisplay === 'stepper';
	const palettes = useMultipleOriginColorsAndGradients();
	const css = useBlockGapCSS( `#block-${ clientId } > .yaycheckout-order-summary-items`, style );
	const productPlaceholder = __( 'Product', 'yaycheckout' );
	const subtotalPlaceholder = __( 'Subtotal', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Product', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show thumbnails', 'yaycheckout' ) }
						checked={ showThumbnails }
						onChange={ ( value ) => setAttributes( { showThumbnails: value } ) }
					/>
					<SelectControl
						label={ __( 'Product details', 'yaycheckout' ) }
						value={ itemData }
						options={ [
							{ label: __( 'List', 'yaycheckout' ), value: 'list' },
							{ label: __( 'Inline', 'yaycheckout' ), value: 'inline' },
							{ label: __( 'Hidden', 'yaycheckout' ), value: 'hidden' },
						] }
						onChange={ ( value ) => setAttributes( { itemData: value as OrderSummaryItemsAttributes[ 'itemData' ] } ) }
					/>
					<ToggleControl
						label={ __( 'Show SKU', 'yaycheckout' ) }
						checked={ showSku }
						onChange={ ( value ) => setAttributes( { showSku: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show unit price', 'yaycheckout' ) }
						checked={ showUnitPrice }
						onChange={ ( value ) => setAttributes( { showUnitPrice: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show short description', 'yaycheckout' ) }
						checked={ showShortDescription }
						onChange={ ( value ) => setAttributes( { showShortDescription: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Quantity', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Show as', 'yaycheckout' ) }
						value={ quantityDisplay }
						options={ [
							{ label: __( 'Inline', 'yaycheckout' ), value: 'inline' },
							{ label: __( 'Stepper', 'yaycheckout' ), value: 'stepper' },
							{ label: __( 'Hidden', 'yaycheckout' ), value: 'hidden' },
						] }
						help={ quantityHelp( quantityDisplay, badge ) }
						onChange={ ( value ) =>
							setAttributes( { quantityDisplay: value as OrderSummaryItemsAttributes[ 'quantityDisplay' ] } )
						}
					/>
					{ showThumbnails && (
						<ToggleControl
							label={ __( 'Badge on thumbnail', 'yaycheckout' ) }
							checked={ showBadge }
							onChange={ ( value ) => setAttributes( { showBadge: value } ) }
						/>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Column labels', 'yaycheckout' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show labels', 'yaycheckout' ) }
						checked={ showLabels }
						onChange={ ( value ) => setAttributes( { showLabels: value } ) }
					/>
					{ showLabels && (
						<>
							<TextControl
								label={ __( 'Product label', 'yaycheckout' ) }
								value={ productLabel ?? '' }
								placeholder={ productPlaceholder }
								onChange={ ( value ) => setAttributes( { productLabel: value } ) }
							/>
							<TextControl
								label={ __( 'Subtotal label', 'yaycheckout' ) }
								value={ subtotalLabel ?? '' }
								placeholder={ subtotalPlaceholder }
								onChange={ ( value ) => setAttributes( { subtotalLabel: value } ) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			{ badge && (
				<InspectorControls group="styles">
					<ToolsPanel
						label={ __( 'Quantity badge', 'yaycheckout' ) }
						panelId={ BADGE_PANEL }
						className="yaycheckout-tools-panel"
						resetAll={ () => setAttributes( { badgeBackgroundColor: '', badgeTextColor: '', badgeSize: undefined } ) }
					>
						<div className="yaycheckout-tools-panel__colors">
							<ColorGradientSettingsDropdown
								__experimentalIsRenderedInSidebar
								panelId={ BADGE_PANEL }
								settings={ [
									{
										label: __( 'Background', 'yaycheckout' ),
										colorValue: badgeBackgroundColor || undefined,
										onColorChange: ( value?: string ) => setAttributes( { badgeBackgroundColor: value ?? '' } ),
										clearable: true,
										isShownByDefault: true,
									},
									{
										label: __( 'Text', 'yaycheckout' ),
										colorValue: badgeTextColor || undefined,
										onColorChange: ( value?: string ) => setAttributes( { badgeTextColor: value ?? '' } ),
										clearable: true,
										isShownByDefault: true,
									},
								] }
								colors={ palettes.colors }
								disableCustomColors={ palettes.disableCustomColors }
							/>
						</div>
						<ToolsPanelItem
							panelId={ BADGE_PANEL }
							label={ __( 'Size', 'yaycheckout' ) }
							hasValue={ () => typeof badgeSize === 'number' }
							onDeselect={ () => setAttributes( { badgeSize: undefined } ) }
							isShownByDefault
						>
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Size', 'yaycheckout' ) }
								value={ badgeSize }
								min={ 16 }
								max={ 32 }
								initialPosition={ 20 }
								allowReset
								onChange={ ( value ) => setAttributes( { badgeSize: value } ) }
							/>
						</ToolsPanelItem>
					</ToolsPanel>
				</InspectorControls>
			) }
			<div { ...useBlockProps( { style: badgeStyle( attributes ) } ) }>
				{ css && <style>{ css }</style> }
				<div
					className={ `yaycheckout-order-summary-items${
						itemData === 'inline' ? ' yaycheckout-order-summary-items--item-data-inline' : ''
					}` }
				>
					{ showLabels && (
						<div className="yaycheckout-order-summary-items__header">
							<span className="product-name">{ plainText( productLabel ) || productPlaceholder }</span>
							<span className="product-total">{ plainText( subtotalLabel ) || subtotalPlaceholder }</span>
						</div>
					) }
					<div className="cart_item yaycheckout-order-summary-items__item">
						{ showThumbnails && (
							<div className="product-thumbnail">
								<div className="yaycheckout-order-summary-items__placeholder" />
								{ badge && (
									<span className="yaycheckout-order-summary-items__badge">
										<span aria-hidden="true">1</span>
									</span>
								) }
							</div>
						) }
						<div className="product-name">
							{ __( 'Sample product', 'yaycheckout' ) }&nbsp;
							{ quantityDisplay === 'inline' && <strong className="product-quantity">×&nbsp;1</strong> }
							{ itemData !== 'hidden' && (
								<dl className="variation">
									<dt>{ __( 'Color:', 'yaycheckout' ) }</dt>
									{ ' ' }
									<dd>
										<p>{ __( 'Blue', 'yaycheckout' ) }</p>
									</dd>
								</dl>
							) }
							{ showSku && <div className="product-sku">{ __( 'SKU:', 'yaycheckout' ) } SAMPLE-01</div> }
							{ showUnitPrice && <div className="product-unit-price">{
										/* translators: %s: product price */
										sprintf( __( '%s each', 'yaycheckout' ), '$20.00' )
									}</div> }
							{ showShortDescription && (
								<div className="product-short-description">{ __( 'A short product description.', 'yaycheckout' ) }</div>
							) }
							{ stepper && (
								<div className="yaycheckout-order-summary-items__controls">
									<div className="yaycheckout-qty">
										<button type="button" className="yaycheckout-qty__button" disabled>
											−
										</button>
										<input type="number" className="yaycheckout-qty__input" value={ 1 } readOnly disabled />
										<button type="button" className="yaycheckout-qty__button" disabled>
											+
										</button>
									</div>
									<span className="remove yaycheckout-order-summary-items__remove">×</span>
								</div>
							) }
						</div>
						<div className="product-total">$20.00</div>
					</div>
				</div>
			</div>
		</>
	);
}
