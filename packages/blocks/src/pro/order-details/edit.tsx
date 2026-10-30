import {
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	THUMBNAIL_RADIUS,
	THUMBNAIL_SIZE,
	thumbnailStyleVars,
	type ThumbnailRatio,
} from '../../shared/thumbnail-style';
import '../../shared/tools-panel.css';
import './editor.css';

interface OrderDetailsAttributes {
	showTitle?: boolean;
	title: string;
	productLabel: string;
	totalLabel: string;
	subtotalLabel: string;
	shippingLabel: string;
	paymentMethodLabel: string;
	orderTotalLabel: string;
	showThumbnails: boolean;
	thumbnailRatio: ThumbnailRatio;
	thumbnailSize?: number;
	thumbnailRadius?: number;
	tableStyle: 'boxed' | 'minimal';
	showDivider: boolean;
	dividerColor: string;
	totalLast: boolean;
	totalColor: string;
}

interface EditProps {
	attributes: OrderDetailsAttributes;
	setAttributes: ( attributes: Partial< OrderDetailsAttributes > ) => void;
}

type LabelKey = 'subtotalLabel' | 'shippingLabel' | 'paymentMethodLabel' | 'orderTotalLabel';

const THUMBNAIL_PANEL = 'yaycheckout-order-details-thumbnail';
const COLORS_PANEL = 'yaycheckout-order-details-colors';

/**
 * Mirrors OrderDetailsTable::wrapper_attributes(): the display options as classes and CSS vars on the
 * wrapper, which the block's style.css reads. Colours are the CSS values the pickers store.
 */
export function orderDetailsWrapperProps( attributes: OrderDetailsAttributes ) {
	const { showThumbnails, tableStyle, showDivider, dividerColor, totalColor } = attributes;
	const minimal = tableStyle === 'minimal';
	const classes = [
		minimal && 'yaycheckout-order-details--minimal',
		minimal && showDivider && 'yaycheckout-order-details--divider',
		totalColor && 'yaycheckout-order-details--total-color',
	].filter( Boolean );

	return {
		className: classes.join( ' ' ),
		style: {
			...( showThumbnails ? thumbnailStyleVars( attributes ) : {} ),
			...( minimal && showDivider && dividerColor ? { '--yaycheckout-order-details-divider': dividerColor } : {} ),
			...( totalColor ? { '--yaycheckout-order-details-total-color': totalColor } : {} ),
		} as Record< string, string >,
	};
}

/**
 * Preview of WooCommerce's order details table. Heading, column and totals-row labels are
 * RichText; empty attributes fall back to WooCommerce's strings on the storefront. Show title
 * hides the WC heading when off. The display options mirror OrderDetailsTable: the sample row
 * takes its markup with a thumbnail, and the totals follow WooCommerce's order (Total, then
 * Payment method) unless Total moves last.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const {
		title,
		productLabel,
		totalLabel,
		showThumbnails,
		thumbnailRatio,
		thumbnailSize,
		thumbnailRadius,
		tableStyle,
		showDivider,
		dividerColor,
		totalLast,
		totalColor,
	} = attributes;
	const showTitle = attributes.showTitle !== false;
	const minimal = tableStyle === 'minimal';
	const wrapper = orderDetailsWrapperProps( attributes );
	const blockProps = useBlockProps( {
		className: `yaycheckout-order-details ${ wrapper.className }`.trim(),
		style: wrapper.style,
	} );
	const palettes = useMultipleOriginColorsAndGradients();

	const labelCell = ( key: LabelKey, placeholder: string ) => (
		<th scope="row">
			<RichText
				tagName="span"
				value={ attributes[ key ] }
				onChange={ ( value: string ) => setAttributes( { [ key ]: value } ) }
				placeholder={ placeholder }
				allowedFormats={ [] }
				withoutInteractiveFormatting
			/>
		</th>
	);

	const amount = <span className="woocommerce-Price-amount amount">{ __( '$0.00', 'yaycheckout' ) }</span>;
	const totalRow = (
		<tr key="total">
			{ labelCell( 'orderTotalLabel', __( 'Total:', 'yaycheckout' ) ) }
			<td>{ totalColor ? <span className="yaycheckout-order-details__total">{ amount }</span> : amount }</td>
		</tr>
	);
	const paymentRow = (
		<tr key="payment">
			{ labelCell( 'paymentMethodLabel', __( 'Payment method:', 'yaycheckout' ) ) }
			<td>{ __( 'Direct bank transfer', 'yaycheckout' ) }</td>
		</tr>
	);
	const productName = __( 'Sample product', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show title', 'yaycheckout' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show thumbnails', 'yaycheckout' ) }
						checked={ showThumbnails }
						onChange={ ( value ) => setAttributes( { showThumbnails: value } ) }
					/>
					<SelectControl
						label={ __( 'Table style', 'yaycheckout' ) }
						value={ tableStyle }
						options={ [
							{ label: __( 'Boxed', 'yaycheckout' ), value: 'boxed' },
							{ label: __( 'Minimal', 'yaycheckout' ), value: 'minimal' },
						] }
						onChange={ ( value ) =>
							setAttributes( { tableStyle: value as OrderDetailsAttributes[ 'tableStyle' ] } )
						}
					/>
					{ minimal && (
						<ToggleControl
							label={ __( 'Show divider', 'yaycheckout' ) }
							help={ __( 'A line between the products and the totals.', 'yaycheckout' ) }
							checked={ showDivider }
							onChange={ ( value ) => setAttributes( { showDivider: value } ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Move total to the end', 'yaycheckout' ) }
						help={ __( 'Shows the order total after the payment method.', 'yaycheckout' ) }
						checked={ totalLast }
						onChange={ ( value ) => setAttributes( { totalLast: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ showThumbnails && (
				<InspectorControls group="styles">
					<ToolsPanel
						label={ __( 'Thumbnail', 'yaycheckout' ) }
						panelId={ THUMBNAIL_PANEL }
						className="yaycheckout-tools-panel"
						resetAll={ () =>
							setAttributes( { thumbnailRatio: 'square', thumbnailSize: undefined, thumbnailRadius: undefined } )
						}
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
								onChange={ ( value ) => setAttributes( { thumbnailRatio: value as ThumbnailRatio } ) }
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							panelId={ THUMBNAIL_PANEL }
							label={ __( 'Size', 'yaycheckout' ) }
							hasValue={ () => typeof thumbnailSize === 'number' }
							onDeselect={ () => setAttributes( { thumbnailSize: undefined } ) }
							isShownByDefault
						>
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Size', 'yaycheckout' ) }
								value={ thumbnailSize }
								{ ...THUMBNAIL_SIZE }
								initialPosition={ 48 }
								allowReset
								onChange={ ( value ) => setAttributes( { thumbnailSize: value } ) }
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
								initialPosition={ 2 }
								allowReset
								onChange={ ( value ) => setAttributes( { thumbnailRadius: value } ) }
							/>
						</ToolsPanelItem>
					</ToolsPanel>
				</InspectorControls>
			) }
			<InspectorControls group="styles">
				<ToolsPanel
					label={ __( 'Totals', 'yaycheckout' ) }
					panelId={ COLORS_PANEL }
					className="yaycheckout-tools-panel"
					resetAll={ () => setAttributes( { dividerColor: '', totalColor: '' } ) }
				>
					<div className="yaycheckout-tools-panel__colors">
						<ColorGradientSettingsDropdown
							__experimentalIsRenderedInSidebar
							panelId={ COLORS_PANEL }
							settings={ [
								{
									label: __( 'Total', 'yaycheckout' ),
									colorValue: totalColor || undefined,
									onColorChange: ( value?: string ) => setAttributes( { totalColor: value ?? '' } ),
									clearable: true,
									isShownByDefault: true,
								},
								...( minimal && showDivider
									? [
											{
												label: __( 'Divider', 'yaycheckout' ),
												colorValue: dividerColor || undefined,
												onColorChange: ( value?: string ) =>
													setAttributes( { dividerColor: value ?? '' } ),
												clearable: true,
												isShownByDefault: true,
											},
									  ]
									: [] ),
							] }
							colors={ palettes.colors }
							disableCustomColors={ palettes.disableCustomColors }
						/>
					</div>
				</ToolsPanel>
			</InspectorControls>
			<div { ...blockProps }>
				<section className="yaycheckout-order-details__preview woocommerce-order-details">
					{ showTitle && (
						<RichText
							tagName="h2"
							className="woocommerce-order-details__title"
							value={ title }
							onChange={ ( value: string ) => setAttributes( { title: value } ) }
							placeholder={ __( 'Order details', 'yaycheckout' ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
					) }
					<table className="woocommerce-table woocommerce-table--order-details shop_table order_details">
						<thead>
							<tr>
								<th className="product-name">
									<RichText
										tagName="span"
										value={ productLabel }
										onChange={ ( value: string ) => setAttributes( { productLabel: value } ) }
										placeholder={ __( 'Product', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
								<th className="product-total">
									<RichText
										tagName="span"
										value={ totalLabel }
										onChange={ ( value: string ) => setAttributes( { totalLabel: value } ) }
										placeholder={ __( 'Total', 'yaycheckout' ) }
										allowedFormats={ [] }
										withoutInteractiveFormatting
									/>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td className="product-name">
									{ showThumbnails ? (
										<span className="yaycheckout-order-details__item">
											<span className="yaycheckout-order-details__thumbnail">
												<span className="yaycheckout-order-details__placeholder" />
											</span>
											<span className="yaycheckout-order-details__name">{ productName }</span>
										</span>
									) : (
										productName
									) }{ ' ' }
									<strong className="product-quantity">&times;&nbsp;1</strong>
								</td>
								<td className="product-total">{ amount }</td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								{ labelCell( 'subtotalLabel', __( 'Subtotal:', 'yaycheckout' ) ) }
								<td>{ amount }</td>
							</tr>
							<tr>
								{ labelCell( 'shippingLabel', __( 'Shipping:', 'yaycheckout' ) ) }
								<td>{ __( 'Free', 'yaycheckout' ) }</td>
							</tr>
							{ totalLast ? [ paymentRow, totalRow ] : [ totalRow, paymentRow ] }
						</tfoot>
					</table>
				</section>
			</div>
		</>
	);
}
