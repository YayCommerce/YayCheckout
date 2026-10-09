import { InspectorControls, useBlockProps, useSettings } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { blockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { plainText } from '../shared/plain-text';
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
	quantityDisplay: 'inline' | 'badge';
	showStepper: boolean;
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
		quantityDisplay,
		showStepper,
		style,
	} = attributes;
	// As order_items_html(): a badge needs a thumbnail to sit on.
	const badge = quantityDisplay === 'badge' && showThumbnails;
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
					<SelectControl
						label={ __( 'Quantity', 'yaycheckout' ) }
						value={ quantityDisplay }
						options={ [
							{ label: __( 'Inline', 'yaycheckout' ), value: 'inline' },
							{ label: __( 'Badge', 'yaycheckout' ), value: 'badge' },
						] }
						help={
							quantityDisplay === 'badge' && ! showThumbnails
								? __( 'Shown inline while thumbnails are off.', 'yaycheckout' )
								: undefined
						}
						onChange={ ( value ) =>
							setAttributes( { quantityDisplay: value as OrderSummaryItemsAttributes[ 'quantityDisplay' ] } )
						}
					/>
					<ToggleControl
						label={ __( 'Quantity stepper', 'yaycheckout' ) }
						help={ __( 'Lets shoppers change or remove items.', 'yaycheckout' ) }
						checked={ showStepper }
						onChange={ ( value ) => setAttributes( { showStepper: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
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
							{ ! badge && ! showStepper && <strong className="product-quantity">×&nbsp;1</strong> }
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
							{ showStepper && (
								<>
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
								</>
							) }
						</div>
						<div className="product-total">$20.00</div>
					</div>
				</div>
			</div>
		</>
	);
}
