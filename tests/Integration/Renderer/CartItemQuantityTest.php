<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\CartItemQuantity;

/** The stepper's cart write keeps WooCommerce's cart rules: a refused change leaves the cart as it was. */
class CartItemQuantityTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        WC()->cart->empty_cart();
    }

    /** Adds a simple product at quantity 1 and returns its cart item key. */
    private function add_product( ?callable $configure = null ): string {
        $product = new \WC_Product_Simple();
        $product->set_regular_price( '10' );

        if ( null !== $configure ) {
            $configure( $product );
        }

        $product->save();

        return (string) WC()->cart->add_to_cart( $product->get_id(), 1 );
    }

    private function quantity( string $key ): int {
        return (int) WC()->cart->get_cart_item( $key )['quantity'];
    }

    public function test_unknown_key_is_refused(): void {
        $key = $this->add_product();

        $this->assertNotNull( CartItemQuantity::apply( WC()->cart, 'nope', 2 ) );
        $this->assertSame( 1, $this->quantity( $key ) );
        $this->assertCount( 1, WC()->cart->get_cart() );
    }

    public function test_zero_removes_the_item(): void {
        $key = $this->add_product();

        $this->assertNull( CartItemQuantity::apply( WC()->cart, $key, 0 ) );
        $this->assertSame( [], WC()->cart->get_cart_item( $key ) );
    }

    public function test_sets_the_quantity(): void {
        $key = $this->add_product();

        $this->assertNull( CartItemQuantity::apply( WC()->cart, $key, 3 ) );
        $this->assertSame( 3, $this->quantity( $key ) );
    }

    public function test_above_max_purchase_quantity_is_refused(): void {
        $key = $this->add_product(
            static function ( \WC_Product $product ) {
                $product->set_manage_stock( true );
                $product->set_stock_quantity( 3 );
                $product->set_backorders( 'no' );
            }
        );

        $this->assertNotNull( CartItemQuantity::apply( WC()->cart, $key, 4 ) );
        $this->assertSame( 1, $this->quantity( $key ) );
    }

    public function test_sold_individually_stays_at_one(): void {
        $key = $this->add_product(
            static function ( \WC_Product $product ) {
                $product->set_sold_individually( true );
            }
        );

        $this->assertNotNull( CartItemQuantity::apply( WC()->cart, $key, 2 ) );
        $this->assertSame( 1, $this->quantity( $key ) );
    }

    public function test_validation_filter_veto_keeps_quantity(): void {
        $key = $this->add_product();
        add_filter( 'woocommerce_update_cart_validation', '__return_false' );

        $this->assertSame( 'The quantity could not be updated.', CartItemQuantity::apply( WC()->cart, $key, 2 ) );
        $this->assertSame( 1, $this->quantity( $key ) );
    }
}
