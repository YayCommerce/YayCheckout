<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Enums\ConditionType;
use YayCheckout\Renderer\CartFacts;

/** A cart item reports what a rule names: a variation its parent, a subcategory its ancestors. */
class CartFactsTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        WC()->cart->empty_cart();
    }

    public function test_variation_reports_its_parent_and_category_ancestors(): void {
        $clothing = self::factory()->term->create(
            [
                'taxonomy' => 'product_cat',
                'name'     => 'Clothing',
            ]
        );
        $shirts   = self::factory()->term->create(
            [
                'taxonomy' => 'product_cat',
                'name'     => 'Shirts',
                'parent'   => $clothing,
            ]
        );

        $parent = new \WC_Product_Variable();
        $parent->set_category_ids( [ $shirts ] );
        $parent->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id( $parent->get_id() );
        $variation->set_regular_price( '10' );
        $variation->save();

        WC()->cart->add_to_cart( $parent->get_id(), 1, $variation->get_id() );

        $items = CartFacts::items( WC()->cart );

        $this->assertCount( 1, $items );
        $this->assertSame( $parent->get_id(), $items[0]['product'] );
        $this->assertEqualsCanonicalizing( [ $shirts, $clothing ], $items[0]['categories'] );
    }

    /**
     * P-1: the layout is picked before the checkout recalculates, so the totals must already be there when
     * WooCommerce restores the cart from the session.
     */
    public function test_context_reads_subtotal_and_quantity(): void {
        $product = new \WC_Product_Simple();
        $product->set_regular_price( '60' );
        $product->save();

        WC()->cart->add_to_cart( $product->get_id(), 2 );
        // The cart outlives a test, and its add-to-cart recalculation hook doesn't: the suite restores hooks.
        WC()->cart->calculate_totals();
        $session = new \WC_Cart_Session( WC()->cart );
        $session->set_session();

        // A new request's cart: nothing in memory until the session restores it.
        WC()->cart->set_cart_contents( [] );
        WC()->cart->set_totals( [] );

        $seen = null;
        add_action(
            'woocommerce_cart_loaded_from_session',
            static function () use ( &$seen ) {
                $seen = CartFacts::context();
            }
        );

        $session->get_cart_from_session();

        $this->assertSame( 120.0, $seen[ ConditionType::CART_SUBTOTAL ] );
        $this->assertSame( 2, $seen[ ConditionType::CART_QUANTITY ] );
    }
}
