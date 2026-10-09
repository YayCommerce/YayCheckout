import { InspectorControls, useBlockProps, useSettings } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { blockGapCSS, type BlockGapStyles } from '../shared/block-gap';
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
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryItemsAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryItemsAttributes > ) => void;
	clientId: string;
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
		style,
	} = attributes;
	const [ viewport ] = useSettings( 'viewport' );
	const css = blockGapCSS( `#block-${ clientId } > .yaycheckout-order-summary-items`, style, viewport );
	const productPlaceholder = __( 'Product', 'yaycheckout' );
	const subtotalPlaceholder = __( 'Subtotal', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order summary items', 'yaycheckout' ) }>
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
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ css && <style>{ css }</style> }
				<div
					className={
						itemData === 'inline'
							? 'yaycheckout-order-summary-items yaycheckout-order-summary-items--item-data-inline'
							: 'yaycheckout-order-summary-items'
					}
				>
					{ showLabels && (
						<div className="yaycheckout-order-summary-items__header">
							<span className="product-name">{ productLabel?.trim() || productPlaceholder }</span>
							<span className="product-total">{ subtotalLabel?.trim() || subtotalPlaceholder }</span>
						</div>
					) }
					<div className="cart_item yaycheckout-order-summary-items__item">
						{ showThumbnails && (
							<div className="product-thumbnail">
								<div className="yaycheckout-order-summary-items__placeholder" />
							</div>
						) }
						<div className="product-name">
							{ __( 'Sample product', 'yaycheckout' ) }&nbsp;
							<strong className="product-quantity">×&nbsp;1</strong>
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
						</div>
						<div className="product-total">$20.00</div>
					</div>
				</div>
			</div>
		</>
	);
}
