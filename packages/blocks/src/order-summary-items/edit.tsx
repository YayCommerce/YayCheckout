import { InspectorControls, useBlockProps, useSettings } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { blockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import './editor.css';

interface OrderSummaryItemsAttributes {
	showLabels: boolean;
	productLabel?: string;
	subtotalLabel?: string;
	showThumbnails: boolean;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryItemsAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryItemsAttributes > ) => void;
	clientId: string;
}

// Mirrors CheckoutFragments::order_items_html(), so the block's style.css draws it as on the storefront.
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { showLabels, productLabel, subtotalLabel, showThumbnails, style } = attributes;
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
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ css && <style>{ css }</style> }
				<div className="yaycheckout-order-summary-items">
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
							{ __( 'Sample product', 'yaycheckout' ) }&nbsp;<strong className="product-quantity">×&nbsp;1</strong>
						</div>
						<div className="product-total">$20.00</div>
					</div>
				</div>
			</div>
		</>
	);
}
