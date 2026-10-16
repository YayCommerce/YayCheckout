<?php
namespace YayCheckout\Tests\Integration\Blocks;

use WP_UnitTestCase;

/** The WooCommerce Hook block prints only what plugins add on an allowed hook, and always a swap target for a refreshed one. */
class WcHookRenderTest extends WP_UnitTestCase {

    public function test_unknown_or_unhooked_hook_prints_nothing(): void {
        $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"init"} /-->' ) ) );
        $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_after_checkout_form"} /-->' ) ) );
    }

    public function test_output_is_wrapped_in_the_block(): void {
        add_action( 'woocommerce_after_checkout_form', [ $this, 'print_probe' ] );
        $html = do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_after_checkout_form"} /-->' );

        $this->assertMatchesRegularExpression( '#<div[^>]*class="[^"]*yaycheckout-wc-hook[^"]*"[^>]*>\s*<div class="yaycheckout-wc-hook__rows"><p>PROBE</p>#', $html );
    }

    public function test_refresh_hook_prints_an_empty_live_root(): void {
        wc_load_cart();
        $html = do_blocks( '<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_review_order_before_order_total"} /-->' );

        $this->assertStringContainsString( 'yaycheckout-wc-hook__content--woocommerce_review_order_before_order_total', $html );
        $this->assertStringContainsString( 'data-yaycheckout-live="1"', $html );
        $this->assertStringContainsString( 'data-yaycheckout-empty="1"', $html );
    }

    public function print_probe(): void {
        echo '<p>PROBE</p>';
    }
}
