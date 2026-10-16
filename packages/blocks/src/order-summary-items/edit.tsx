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
import type { ReactNode } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { preventCanvasNavigation } from '../shared/canvas-link';
import { plainText } from '../shared/plain-text';
import '../shared/tools-panel.css';
import './editor.css';

interface OrderSummaryItemsAttributes {
	showLabels: boolean;
	productLabel?: string;
	subtotalLabel?: string;
	showThumbnails: boolean;
	linkImage: boolean;
	linkName: boolean;
	itemData: 'list' | 'inline' | 'hidden';
	showSku: boolean;
	showUnitPrice: boolean;
	showShortDescription: boolean;
	quantityDisplay: 'inline' | 'stepper' | 'hidden';
	showBadge: boolean;
	badgeBackgroundColor: string;
	badgeTextColor: string;
	badgeSize?: number;
	stepperStyle: 'boxed' | 'round';
	stepperBorderColor: string;
	stepperSize?: number;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryItemsAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryItemsAttributes > ) => void;
	clientId: string;
}

const BADGE_PANEL = 'yaycheckout-order-summary-items-badge';
const STEPPER_PANEL = 'yaycheckout-order-summary-items-stepper';

// Mirrors the px ranges CheckoutFragments::order_items_style_vars() clamps badgeSize and stepperSize to.
const BADGE_SIZE = { min: 16, max: 32 };
const STEPPER_SIZE = { min: 20, max: 40 };

function clampedPx( value: number, { min, max }: { min: number; max: number } ) {
	return `${ Math.max( min, Math.min( max, value ) ) }px`;
}

// Mirrors CheckoutFragments::order_items_style_vars(): the badge and stepper settings as --yaycheckout-badge-* and --yaycheckout-stepper-* vars.
function itemsStyleVars( {
	badgeBackgroundColor,
	badgeTextColor,
	badgeSize,
	stepperBorderColor,
	stepperSize,
}: OrderSummaryItemsAttributes ) {
	return {
		...( badgeBackgroundColor ? { '--yaycheckout-badge-bg': badgeBackgroundColor } : {} ),
		...( badgeTextColor ? { '--yaycheckout-badge-text': badgeTextColor } : {} ),
		...( typeof badgeSize === 'number' ? { '--yaycheckout-badge-size': clampedPx( badgeSize, BADGE_SIZE ) } : {} ),
		...( stepperBorderColor ? { '--yaycheckout-stepper-border': stepperBorderColor } : {} ),
		...( typeof stepperSize === 'number' ? { '--yaycheckout-stepper-size': clampedPx( stepperSize, STEPPER_SIZE ) } : {} ),
	};
}

// The image or name link of order_items_html(); decorative when the name carries the same link.
function ItemLink( { on, decorative = false, children }: { on: boolean; decorative?: boolean; children: ReactNode } ) {
	if ( ! on ) {
		return <>{ children }</>;
	}

	return (
		<a
			className="yaycheckout-order-summary-items__link"
			href="#"
			onClick={ preventCanvasNavigation }
			{ ...( decorative ? { tabIndex: -1, 'aria-hidden': true } : {} ) }
		>
			{ children }
		</a>
	);
}

interface ColorSizePanelProps {
	label: string;
	panelId: string;
	resetAll: () => void;
	colors: { label: string; value: string; onChange: ( value: string ) => void }[];
	size: { value?: number; min: number; max: number; initial: number; onChange: ( value?: number ) => void };
	// Items shown before the colours, such as the stepper's Shape.
	children?: ReactNode;
}

// A Styles tab panel of colours and a size, as the badge and the stepper have.
function ColorSizePanel( { label, panelId, resetAll, colors, size, children }: ColorSizePanelProps ) {
	const palettes = useMultipleOriginColorsAndGradients();

	return (
		<InspectorControls group="styles">
			<ToolsPanel label={ label } panelId={ panelId } className="yaycheckout-tools-panel" resetAll={ resetAll }>
				{ children }
				<div className="yaycheckout-tools-panel__colors">
					<ColorGradientSettingsDropdown
						__experimentalIsRenderedInSidebar
						panelId={ panelId }
						settings={ colors.map( ( color ) => ( {
							label: color.label,
							colorValue: color.value || undefined,
							onColorChange: ( value?: string ) => color.onChange( value ?? '' ),
							clearable: true,
							isShownByDefault: true,
						} ) ) }
						colors={ palettes.colors }
						disableCustomColors={ palettes.disableCustomColors }
					/>
				</div>
				<ToolsPanelItem
					panelId={ panelId }
					label={ __( 'Size', 'yaycheckout' ) }
					hasValue={ () => typeof size.value === 'number' }
					onDeselect={ () => size.onChange( undefined ) }
					isShownByDefault
				>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Size', 'yaycheckout' ) }
						value={ size.value }
						min={ size.min }
						max={ size.max }
						initialPosition={ size.initial }
						allowReset
						onChange={ size.onChange }
					/>
				</ToolsPanelItem>
			</ToolsPanel>
		</InspectorControls>
	);
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
		linkImage,
		linkName,
		itemData,
		showSku,
		showUnitPrice,
		showShortDescription,
		quantityDisplay,
		showBadge,
		badgeBackgroundColor,
		badgeTextColor,
		badgeSize,
		stepperStyle,
		stepperBorderColor,
		stepperSize,
		style,
	} = attributes;
	// As order_items_html(): a badge needs a thumbnail to sit on, and is independent of quantityDisplay.
	const badge = showBadge && showThumbnails;
	const stepper = quantityDisplay === 'stepper';
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
					{ showThumbnails && (
						<ToggleControl
							label={ __( 'Link image', 'yaycheckout' ) }
							checked={ linkImage }
							onChange={ ( value ) => setAttributes( { linkImage: value } ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Link name', 'yaycheckout' ) }
						help={ __( 'Opens the product page in a new tab.', 'yaycheckout' ) }
						checked={ linkName }
						onChange={ ( value ) => setAttributes( { linkName: value } ) }
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
				<ColorSizePanel
					label={ __( 'Quantity badge', 'yaycheckout' ) }
					panelId={ BADGE_PANEL }
					resetAll={ () => setAttributes( { badgeBackgroundColor: '', badgeTextColor: '', badgeSize: undefined } ) }
					colors={ [
						{
							label: __( 'Background', 'yaycheckout' ),
							value: badgeBackgroundColor,
							onChange: ( value ) => setAttributes( { badgeBackgroundColor: value } ),
						},
						{
							label: __( 'Text', 'yaycheckout' ),
							value: badgeTextColor,
							onChange: ( value ) => setAttributes( { badgeTextColor: value } ),
						},
					] }
					size={ {
						value: badgeSize,
						...BADGE_SIZE,
						initial: 20,
						onChange: ( value ) => setAttributes( { badgeSize: value } ),
					} }
				/>
			) }
			{ stepper && (
				<ColorSizePanel
					label={ __( 'Quantity stepper', 'yaycheckout' ) }
					panelId={ STEPPER_PANEL }
					resetAll={ () => setAttributes( { stepperStyle: 'boxed', stepperBorderColor: '', stepperSize: undefined } ) }
					colors={ [
						{
							label: __( 'Border', 'yaycheckout' ),
							value: stepperBorderColor,
							onChange: ( value ) => setAttributes( { stepperBorderColor: value } ),
						},
					] }
					size={ {
						value: stepperSize,
						...STEPPER_SIZE,
						// The --yaycheckout-qty-size fallbacks in style.css: 24px round, 32px boxed.
						initial: stepperStyle === 'round' ? 24 : 32,
						onChange: ( value ) => setAttributes( { stepperSize: value } ),
					} }
				>
					<ToolsPanelItem
						panelId={ STEPPER_PANEL }
						label={ __( 'Shape', 'yaycheckout' ) }
						hasValue={ () => stepperStyle !== 'boxed' }
						onDeselect={ () => setAttributes( { stepperStyle: 'boxed' } ) }
						isShownByDefault
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Shape', 'yaycheckout' ) }
							value={ stepperStyle }
							options={ [
								{ label: __( 'Boxed', 'yaycheckout' ), value: 'boxed' },
								{ label: __( 'Round', 'yaycheckout' ), value: 'round' },
							] }
							onChange={ ( value ) =>
								setAttributes( { stepperStyle: value as OrderSummaryItemsAttributes[ 'stepperStyle' ] } )
							}
						/>
					</ToolsPanelItem>
				</ColorSizePanel>
			) }
			<div { ...useBlockProps( { style: itemsStyleVars( attributes ) } ) }>
				{ css && <style>{ css }</style> }
				<div
					className={ `yaycheckout-order-summary-items${
						itemData === 'inline' ? ' yaycheckout-order-summary-items--item-data-inline' : ''
					}${ stepper && stepperStyle === 'round' ? ' yaycheckout-order-summary-items--stepper-round' : '' }` }
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
								<ItemLink on={ linkImage } decorative={ linkName }>
									<div className="yaycheckout-order-summary-items__placeholder" />
								</ItemLink>
								{ badge && (
									<span className="yaycheckout-order-summary-items__badge">
										<span aria-hidden="true">1</span>
									</span>
								) }
							</div>
						) }
						<div className="product-name">
							<ItemLink on={ linkName }>{ __( 'Sample product', 'yaycheckout' ) }</ItemLink>
							&nbsp;
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
