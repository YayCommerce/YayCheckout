<?php
namespace YayCheckout\Renderer;

use YayCheckout\Enums\ConditionType;

defined( 'ABSPATH' ) || exit;

/**
 * The cart facts assignment conditions test: per item, its product and categories.
 */
class CartFacts {

    /**
     * The cart's part of the assignment context, keyed by ConditionType. Empty until WooCommerce loads
     * the cart from the session (wp_loaded), so a cart condition stays undecided rather than testing an empty cart.
     */
    public static function context(): array {
        if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) || ! WC()->cart instanceof \WC_Cart ) {
            return [];
        }

        return [ ConditionType::CART_ITEMS => self::items( WC()->cart ) ];
    }

    /**
     * A variation's item counts as its parent: product_id is the parent and variation_id the variation.
     *
     * @return array<int, array{product: int, categories: int[]}>
     */
    public static function items( \WC_Cart $cart ): array {
        $items = [];

        foreach ( $cart->get_cart() as $item ) {
            $product = (int) $item['product_id'];
            $items[] = [
                'product'    => $product,
                'categories' => self::categories( $product ),
            ];
        }

        return $items;
    }

    /** The product's categories and every ancestor of them: a product in Shirts is also in Clothing. */
    private static function categories( int $product ): array {
        $terms = wc_get_product_term_ids( $product, 'product_cat' );
        $ids   = $terms;

        foreach ( $terms as $term ) {
            $ids = array_merge( $ids, get_ancestors( $term, 'product_cat', 'taxonomy' ) );
        }

        return array_values( array_unique( array_map( 'intval', $ids ) ) );
    }
}
