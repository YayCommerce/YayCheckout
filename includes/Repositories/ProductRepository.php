<?php
namespace YayCheckout\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce product reads.
 */
class ProductRepository {

    /**
     * Product names by id; a deleted product has no entry.
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    public function names( array $ids ): array {
        // An empty `include` means no filter: wc_get_products() would return every product.
        if ( [] === $ids ) {
            return [];
        }

        $products = wc_get_products(
            [
                'include' => $ids,
                'limit'   => -1,
            ]
        );

        $names = [];
        foreach ( $products as $product ) {
            $names[ $product->get_id() ] = $product->get_name();
        }

        return $names;
    }
}
