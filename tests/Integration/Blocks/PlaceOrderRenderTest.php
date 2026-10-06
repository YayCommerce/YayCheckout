<?php
namespace YayCheckout\Tests\Integration\Blocks;

use WP_UnitTestCase;

/** The Place order block renders a styled submit button that keeps WooCommerce's submit contract. */
class PlaceOrderRenderTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        WC()->cart = null;
    }

    public function test_without_cart_is_a_disabled_styled_submit_button(): void {
        $html = do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":"Pay now","style":{"color":{"background":"#cc3300"},"border":{"radius":"4px"}}} /-->' );

        $this->assertMatchesRegularExpression( '#^\s*<button\b[^>]*\bid="place_order"#', $html );
        $this->assertMatchesRegularExpression( '#<button[^>]*\btype="submit"#', $html );
        $this->assertStringContainsString( 'name="woocommerce_checkout_place_order"', $html );
        $this->assertStringContainsString( 'data-value="Pay now"', $html );
        $this->assertMatchesRegularExpression( '~<button[^>]*style="[^"]*background-color:\s*#cc3300~', $html );
        $this->assertMatchesRegularExpression( '#<button[^>]*style="[^"]*border-radius:\s*4px#', $html );
        $this->assertMatchesRegularExpression( '#<button[^>]*class="[^"]*yaycheckout-place-order wp-element-button#', $html );
        $this->assertMatchesRegularExpression( '#<button[^>]*\bdisabled#', $html );
        $this->assertStringNotContainsString( 'button alt', $html );
    }

    public function test_with_cart_prints_terms_hooks_and_nonce(): void {
        wc_load_cart();
        $html = do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":"Pay now"} /-->' );

        $this->assertStringContainsString( 'form-row place-order', $html );
        $this->assertStringContainsString( 'woocommerce-process-checkout-nonce', $html );
        $this->assertDoesNotMatchRegularExpression( '#<button[^>]*\bdisabled#', $html );
    }

    public function test_default_text_runs_the_button_text_filter_and_custom_text_does_not(): void {
        add_filter( 'woocommerce_order_button_text', static fn() => 'Sign up now' );
        $default = do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":"Place order"} /-->' );
        $empty   = do_blocks( '<!-- wp:yaycheckout/place-order /-->' );
        $custom  = do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":"Pay now"} /-->' );

        $this->assertStringContainsString( '>Sign up now</button>', $default );
        $this->assertStringContainsString( 'data-value="Sign up now"', $empty );
        $this->assertStringContainsString( '>Pay now</button>', $custom );
    }

    public function test_old_markup_renders_its_text(): void {
        $this->assertStringContainsString( '>Buy</button>', do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":"Buy"} /-->' ) );
    }

    public function test_empty_text_falls_back_to_default_label(): void {
        $this->assertStringContainsString( '>Place order</button>', do_blocks( '<!-- wp:yaycheckout/place-order {"buttonText":""} /-->' ) );
    }
}
