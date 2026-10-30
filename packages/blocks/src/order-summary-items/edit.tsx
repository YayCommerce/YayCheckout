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
import { HookOutputPreview } from '../shared/hook-output-preview';
import { plainText } from '../shared/plain-text';
import {
	clampedPx,
	THUMBNAIL_RADIUS,
	THUMBNAIL_SIZE,
	thumbnailStyleVars,
	type ThumbnailRatio,
} from '../shared/thumbnail-style';
import '../shared/tools-panel.css';
import './editor.css';

interface OrderSummaryItemsAttributes {
	showLabels: boolean;
	productLabel?: string;
	subtotalLabel?: string;
	showThumbnails: boolean;
	thumbnailRatio: ThumbnailRatio;
	thumbnailSize?: number;
	thumbnailRadius?: number;
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
const THUMBNAIL_PANEL = 'yaycheckout-order-summary-items-thumbnail';

// Mirrors the px ranges CheckoutFragments::order_items_style_vars() clamps badgeSize and stepperSize to.
const BADGE_SIZE = { min: 16, max: 32 };
const STEPPER_SIZE = { min: 20, max: 40 };

// Mirrors CheckoutFragments::order_items_style_vars(): the badge, stepper and thumbnail settings as
// --yaycheckout-badge-*, --yaycheckout-stepper-* and --yaycheckout-thumbnail-* vars.
function itemsStyleVars( attributes: OrderSummaryItemsAttributes ) {
	const { badgeBackgroundColor, badgeTextColor, badgeSize, stepperBorderColor, stepperSize } = attributes;

	return {
		...( badgeBackgroundColor ? { '--yaycheckout-badge-bg': badgeBackgroundColor } : {} ),
		...( badgeTextColor ? { '--yaycheckout-badge-text': badgeTextColor } : {} ),
		...( typeof badgeSize === 'number' ? { '--yaycheckout-badge-size': clampedPx( badgeSize, BADGE_SIZE ) } : {} ),
		...( stepperBorderColor ? { '--yaycheckout-stepper-border': stepperBorderColor } : {} ),
		...( typeof stepperSize === 'number' ? { '--yaycheckout-stepper-size': clampedPx( stepperSize, STEPPER_SIZE ) } : {} ),
		...thumbnailStyleVars( attributes ),
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
	colors?: { label: string; value: string; onChange: ( value: string ) => void }[];
	size: { value?: number; min: number; max: number; initial: number; onChange: ( value?: number ) => void };
	// Items shown before the colours, such as the stepper's Shape.
	children?: ReactNode;
}

// A Styles tab panel of colours and a size, as the badge and the stepper have; the thumbnail's has no colours.
function ColorSizePanel( { label, panelId, resetAll, colors = [], size, children }: ColorSizePanelProps ) {
	const palettes = useMultipleOriginColorsAndGradients();

	return (
		<InspectorControls group="styles">
			<ToolsPanel label={ label } panelId={ panelId } className="yaycheckout-tools-panel" resetAll={ resetAll }>
				{ children }
				{ colors.length > 0 && (
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
				) }
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
		thumbnailRatio,
		thumbnailSize,
		thumbnailRadius,
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
			{ showThumbnails && (
				<ColorSizePanel
					label={ __( 'Thumbnail', 'yaycheckout' ) }
					panelId={ THUMBNAIL_PANEL }
					resetAll={ () =>
						setAttributes( { thumbnailRatio: 'square', thumbnailSize: undefined, thumbnailRadius: undefined } )
					}
					size={ {
						value: thumbnailSize,
						...THUMBNAIL_SIZE,
						initial: 48,
						onChange: ( value ) => setAttributes( { thumbnailSize: value } ),
					} }
				>
					<ToolsPanelItem
						panelId={ THUMBNAIL_PANEL }
						label={ __( 'Aspect ratio', 'yaycheckout' ) }
						hasValue={ () => thumbnailRatio !== 'square' }
						onDeselect={ () => setAttributes( { thumbnailRatio: 'square' } ) }
						isShownByDefault
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Aspect ratio', 'yaycheckout' ) }
							value={ thumbnailRatio }
							options={ [
								{ label: __( 'Square', 'yaycheckout' ), value: 'square' },
								{ label: __( 'Portrait (3:4)', 'yaycheckout' ), value: 'portrait' },
								{ label: __( 'Landscape (4:3)', 'yaycheckout' ), value: 'landscape' },
								{ label: __( 'Original', 'yaycheckout' ), value: 'original' },
							] }
							help={
								thumbnailRatio === 'original'
									? __( 'Each image keeps its own shape; the editor shows a square.', 'yaycheckout' )
									: undefined
							}
							onChange={ ( value ) =>
								setAttributes( { thumbnailRatio: value as OrderSummaryItemsAttributes[ 'thumbnailRatio' ] } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						panelId={ THUMBNAIL_PANEL }
						label={ __( 'Radius', 'yaycheckout' ) }
						hasValue={ () => typeof thumbnailRadius === 'number' }
						onDeselect={ () => setAttributes( { thumbnailRadius: undefined } ) }
						isShownByDefault
					>
						<RangeControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Radius', 'yaycheckout' ) }
							help={ __( 'Unset, it follows the Inputs radius in Layout design.', 'yaycheckout' ) }
							value={ thumbnailRadius }
							{ ...THUMBNAIL_RADIUS }
							// checkout-base.css's --yaycheckout-neutral-radius, the fallback with no Inputs radius.
							initialPosition={ 2 }
							allowReset
							onChange={ ( value ) => setAttributes( { thumbnailRadius: value } ) }
						/>
					</ToolsPanelItem>
				</ColorSizePanel>
			) }
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
					{ /* Mirrors CheckoutFragments::order_items_html()'s print_order_items_hook() after the rows. */ }
					<HookOutputPreview hook="woocommerce_review_order_after_cart_contents" owner="block" />
				</div>
			</div>
		</>
	);
}
