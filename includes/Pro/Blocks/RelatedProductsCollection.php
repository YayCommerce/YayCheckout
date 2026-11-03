<?php
namespace YayCheckout\Pro\Blocks;

use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Pro\Renderer\ThankYouPage;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: the Related products collection of WooCommerce's Product Collection block
 * (packages/blocks/src/pro/related-products/). It lists the products related to the cart's items on the
 * checkout, or to the order's items on the thank-you page, never those items themselves.
 *
 * WooCommerce has no public API for a collection's PHP query, so this rewrites the query Product Collection
 * built, on query_loop_block_query_vars after WooCommerce's own filter. An empty list renders nothing:
 * WooCommerce skips the whole block when its product template is empty and it has no No results block.
 */
class RelatedProductsCollection {

    use SingletonTrait;

    /** The collection's name. COLLECTION in packages/blocks/src/pro/related-products/index.ts copies it. */
    public const COLLECTION = 'yaycheckout/product-collection/related-to-order';

    private const EDITOR_SCRIPT = 'yaycheckout-related-products-editor';

    /** The checkout refresh after the list's Add to cart (packages/blocks/src/pro/related-products/view.ts). */
    private const CHECKOUT_SCRIPT = 'yaycheckout-related-products-checkout';

    /** WooCommerce's handle for __experimentalRegisterProductCollection(). */
    private const WC_REGISTRY_SCRIPT = 'wc-blocks-registry';

    /** Related products asked for per source product, as WooCommerce's own Related collection does. */
    private const RELATED_PER_PRODUCT = 100;

    protected function __construct() {
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor' ] );
        // After WooCommerce's ProductCollection\Controller::build_frontend_query() at 10.
        add_filter( 'query_loop_block_query_vars', [ $this, 'frontend_query' ], 20, 2 );
        // After WooCommerce's Renderer::handle_rendering() at 10, which empties a collection with no products.
        add_filter( 'render_block_woocommerce/product-collection', [ $this, 'enqueue_checkout_refresh' ], 20, 2 );
    }

    /**
     * On a checkout YayCheckout serves, while this collection renders: the script that refreshes the order
     * review after its Add to cart, so the summary and totals show the added product before the shopper pays.
     * The thank-you page has no cart to refresh.
     *
     * @param string $content
     * @param array  $parsed_block
     * @return string
     */
    public function enqueue_checkout_refresh( $content, $parsed_block ) {
        $is_ours = is_array( $parsed_block ) && self::COLLECTION === ( $parsed_block['attrs']['collection'] ?? '' );

        if ( ! $is_ours || '' === $content || is_order_received_page() || null === CheckoutPage::get_instance()->layout() ) {
            return $content;
        }

        $asset = BlockRegistry::built_asset( 'pro/related-products/view' );

        if ( null !== $asset ) {
            wp_enqueue_script( self::CHECKOUT_SCRIPT, $asset['url'] . '.js', array_merge( $asset['dependencies'], [ 'jquery', 'wc-checkout' ] ), $asset['version'], true );
        }

        return $content;
    }

    /** The collection's registration, in the layout editor only, while WooCommerce's blocks are loaded. */
    public function enqueue_editor(): void {
        if ( wp_script_is( self::WC_REGISTRY_SCRIPT, 'registered' ) ) {
            BlockRegistry::get_instance()->enqueue_layout_editor_script( self::EDITOR_SCRIPT, 'pro/related-products/index', [ self::WC_REGISTRY_SCRIPT ] );
        }
    }

    /**
     * Narrows this collection's query to the related products. Other Query Loop and Product Collection
     * blocks pass through.
     *
     * @param array     $query
     * @param \WP_Block $block
     * @return array
     */
    public function frontend_query( $query, $block ) {
        if ( ! is_array( $query ) || ! $block instanceof \WP_Block || self::COLLECTION !== ( $block->context['collection'] ?? '' ) ) {
            return $query;
        }

        $source_ids = self::source_ids( $block->context['productCollectionLocation'] ?? null );
        $related    = self::related_ids( $source_ids );

        // WooCommerce's post__in, when set, already narrows the list (an applied filter); keep both.
        if ( ! empty( $query['post__in'] ) ) {
            $related = array_values( array_intersect( $related, array_map( 'absint', (array) $query['post__in'] ) ) );
        }

        // The source products are left out by related_ids() alone: WP_Query ignores post__not_in beside post__in.
        $query['post__in'] = empty( $related ) ? [ -1 ] : $related;

        return $query;
    }

    /**
     * The product ids the list relates to, from WooCommerce's location context: the cart's on the checkout,
     * the order's on the thank-you page. The order is ThankYouPage's, whose order key was checked, rather
     * than the bare order-received id in the context. Parent ids, so a variation's siblings stay out too.
     *
     * @param mixed $location
     * @return int[]
     */
    private static function source_ids( $location ): array {
        $type = is_array( $location ) ? ( $location['type'] ?? '' ) : '';

        if ( 'cart' === $type ) {
            return array_values( array_unique( array_filter( array_map( 'absint', (array) ( $location['sourceData']['productIds'] ?? [] ) ) ) ) );
        }

        if ( 'order' !== $type ) {
            return [];
        }

        $order = ThankYouPage::get_instance()->order();

        if ( null === $order ) {
            return [];
        }

        $ids = [];

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( $item instanceof \WC_Order_Item_Product ) {
                $ids[] = absint( $item->get_product_id() );
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Every source product's related products (WooCommerce's categories-and-tags relation), without the
     * source products.
     *
     * @param int[] $source_ids
     * @return int[]
     */
    private static function related_ids( array $source_ids ): array {
        $related = [];

        foreach ( $source_ids as $product_id ) {
            $related = array_merge( $related, array_map( 'absint', wc_get_related_products( $product_id, self::RELATED_PER_PRODUCT, $source_ids ) ) );
        }

        return array_values( array_diff( array_unique( $related ), $source_ids ) );
    }
}
