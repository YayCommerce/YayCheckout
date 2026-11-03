/**
 * Pro: the Related products collection, a variation of WooCommerce's Product Collection block, so it keeps
 * Product Collection's inner blocks (product template, image, title, price, button). It lists products
 * related to the items in the cart (checkout) or the order (thank-you page), without those items.
 * RelatedProductsCollection (includes/Pro/Blocks/) enqueues this in the layout editor and builds the query.
 */
import { __ } from '@wordpress/i18n';
import { relatedProductsIcon } from '../../shared/block-icons';

/** Mirrors RelatedProductsCollection::COLLECTION. */
const COLLECTION = 'yaycheckout/product-collection/related-to-order';

type RegisterProductCollection = ( collection: Record< string, unknown > ) => void;

const register = (
	window as unknown as {
		wc?: { wcBlocksRegistry?: { __experimentalRegisterProductCollection?: RegisterProductCollection } };
	}
 ).wc?.wcBlocksRegistry?.__experimentalRegisterProductCollection;

register?.( {
	name: COLLECTION,
	title: __( 'Related products', 'yaycheckout' ),
	description: __( 'Products related to the items in the cart or the order, without those items.', 'yaycheckout' ),
	// The YayCheckout inserter group (BlockRegistry::CATEGORY), not Product Collection's WooCommerce one.
	category: 'yaycheckout',
	icon: relatedProductsIcon,
	keywords: [ 'related', 'upsell', 'cross-sell', 'recommend' ],
	scope: [ 'inserter', 'block' ],
	// No 'product': the reference is never a single product, and naming it makes Woo ask the merchant for one.
	usesReference: [ 'cart', 'order' ],
	attributes: {
		displayLayout: { type: 'flex', columns: 4, shrinkColumns: true },
		query: { perPage: 4, pages: 1 },
		// The query is post__in from PHP; these controls would only narrow it, or do nothing.
		hideControls: [ 'inherit', 'hand-picked', 'keyword', 'attributes', 'taxonomy', 'featured', 'on-sale', 'filterable', 'created', 'price-range' ],
	},
	preview: {
		initialPreviewState: {
			isPreview: true,
			previewMessage: __( 'Actual products will be related to the items in the shopper’s cart or order.', 'yaycheckout' ),
		},
	},
	innerBlocks: [
		[
			'core/heading',
			{
				textAlign: 'center',
				level: 2,
				content: __( 'You may also like', 'yaycheckout' ),
				style: { spacing: { margin: { bottom: '1rem' } } },
			},
		],
		// Woo's own product template, as its collections insert it.
		[
			'woocommerce/product-template',
			{},
			[
				[
					'woocommerce/product-image',
					{ showSaleBadge: false, style: { dimensions: { aspectRatio: '1/1' } } },
					[ [ 'woocommerce/product-sale-badge', { align: 'right' } ] ],
				],
				[
					'core/post-title',
					{
						textAlign: 'center',
						level: 3,
						fontSize: 'medium',
						style: { spacing: { margin: { bottom: '0.75rem', top: '0' } }, typography: { lineHeight: '1.4' } },
						isLink: true,
						__woocommerceNamespace: 'woocommerce/product-collection/product-title',
					},
				],
				[ 'woocommerce/product-price', { textAlign: 'center', fontSize: 'small' } ],
				[ 'woocommerce/product-button', { textAlign: 'center', fontSize: 'small' } ],
			],
		],
	],
} );
