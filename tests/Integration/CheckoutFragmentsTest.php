<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Renderer\CheckoutFragments;
use YayCheckout\Tests\Support\LayoutFactory;

/** The update_order_review refresh swaps only the fragments the served layout renders. */
class CheckoutFragmentsTest extends WP_UnitTestCase {

    private const WOO = [ '.woocommerce-checkout-review-order-table' => '<table></table>' ];

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
    }

    public function tear_down(): void {
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    public function test_layout_with_payment_and_shipping_swaps_both(): void {
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/shipping-methods /--><!-- wp:yaycheckout/payment /--><!-- /wp:yaycheckout/checkout -->' );

        $fragments = CheckoutFragments::get_instance()->fragments( self::WOO );

        $this->assertSame(
            [ '.woocommerce-checkout-review-order-table', '.woocommerce-checkout-payment', '.yaycheckout-shipping-methods__rates' ],
            array_keys( $fragments )
        );
        $this->assertStringContainsString( 'id="payment"', $fragments['.woocommerce-checkout-payment'] );
        $this->assertStringContainsString( 'class="yaycheckout-shipping-methods__rates"', $fragments['.yaycheckout-shipping-methods__rates'] );
    }

    public function test_layout_without_those_blocks_leaves_fragments_alone(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        $this->assertSame( self::WOO, CheckoutFragments::get_instance()->fragments( self::WOO ) );
    }

    public function test_no_served_layout_leaves_fragments_alone(): void {
        LayoutFactory::forget_served_layout();

        $this->assertSame( self::WOO, CheckoutFragments::get_instance()->fragments( self::WOO ) );
    }
}
