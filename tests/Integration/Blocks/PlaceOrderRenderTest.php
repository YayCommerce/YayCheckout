<?php
namespace YayCheckout\Tests\Integration\Blocks;

use WP_UnitTestCase;

/** The Place order block keeps WooCommerce's submit contract on top of the core button. */
class PlaceOrderRenderTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        WC()->cart = null;
    }

    public function test_new_markup_without_cart_is_a_disabled_submit_button(): void {
        $html = do_blocks( $this->markup( 'Pay now' ) );

        $this->assertMatchesRegularExpression( '#<button[^>]*\bid="place_order"#', $html );
        $this->assertStringContainsString( 'name="woocommerce_checkout_place_order"', $html );
        $this->assertStringContainsString( 'data-value="Pay now"', $html );
        $this->assertStringContainsString( 'wp-block-button__link', $html );
        $this->assertMatchesRegularExpression( '#<button[^>]*\bdisabled#', $html );
        $this->assertStringNotContainsString( 'button alt', $html );
    }

    public function test_new_markup_with_cart_prints_terms_hooks_and_nonce(): void {
        wc_load_cart();
        $html = do_blocks( $this->markup( 'Pay now' ) );

        $this->assertStringContainsString( 'woocommerce-process-checkout-nonce', $html );
        $this->assertStringContainsString( 'form-row place-order', $html );
        $this->assertDoesNotMatchRegularExpression( '#<button[^>]*\bdisabled#', $html );
    }

    public function test_default_text_runs_the_button_text_filter_and_custom_text_does_not(): void {
        add_filter( 'woocommerce_order_button_text', static fn() => 'Sign up now' );
        $default = do_blocks( $this->markup( 'Place order' ) );
        $custom  = do_blocks( $this->markup( 'Pay now' ) );

        $this->assertStringContainsString( 'data-value="Sign up now"', $default );
        $this->assertStringContainsString( '>Sign up now</button>', $default );
        $this->assertStringContainsString( 'data-value="Pay now"', $custom );
    }

    public function test_old_markup_renders_legacy_button(): void {
        $html = do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":"Buy"} /-->' );

        $this->assertStringContainsString( '>Buy</button>', $html );
        $this->assertStringContainsString( 'class="button alt"', $html );
    }

    public function test_empty_text_falls_back_to_default_button(): void {
        $html = do_blocks( $this->markup( '' ) );

        $this->assertStringContainsString( '>Place order</button>', $html );
    }

    /** What the editor saves for a Place order block, with $text on the button. */
    private function markup( string $text ): string {
        return '<!-- wp:yaycheckout/place-order -->
<!-- wp:buttons {"lock":{"move":true,"remove":true}} -->
<div class="wp-block-buttons"><!-- wp:button {"tagName":"button","type":"submit","lock":{"move":true,"remove":true},"style":{"dimensions":{"width":"100%"}}} -->
<div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">' . $text . '</button></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:yaycheckout/place-order -->';
    }
}
