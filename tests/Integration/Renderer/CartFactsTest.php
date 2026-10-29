<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
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
}
