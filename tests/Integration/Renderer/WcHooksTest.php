<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\WcHooks;
use YayCheckout\Tests\Support\LayoutFactory;

/** A hook prints once: where a block has always fired it, or at the Hook block placed for it, never with WooCommerce's own forms. */
class WcHooksTest extends WP_UnitTestCase {

    private const SUBMIT = 'woocommerce_review_order_before_submit';

    public function tear_down(): void {
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    public function test_in_place_fires_when_no_hook_block_is_placed(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        add_action( self::SUBMIT, [ $this, 'print_probe' ] );

        ob_start();
        WcHooks::in_place( self::SUBMIT );
        $this->assertSame( 'PROBE', ob_get_clean() );
    }

    public function test_in_place_is_skipped_when_a_hook_block_is_placed(): void {
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/wc-hook {"hook":"' . self::SUBMIT . '"} /--><!-- /wp:yaycheckout/checkout -->' );
        add_action( self::SUBMIT, [ $this, 'print_probe' ] );

        ob_start();
        WcHooks::in_place( self::SUBMIT );
        $this->assertSame( '', ob_get_clean() );
        $this->assertSame( 'PROBE', WcHooks::fire( self::SUBMIT ) );
    }

    public function test_fire_skips_core_callbacks_and_restores_them(): void {
        wc_load_cart();
        // WC_Checkout adds this once, when first built; a test's hook reset may have dropped it since.
        if ( false === has_action( 'woocommerce_checkout_billing', [ WC()->checkout(), 'checkout_form_billing' ] ) ) {
            add_action( 'woocommerce_checkout_billing', [ WC()->checkout(), 'checkout_form_billing' ] );
        }
        add_action( 'woocommerce_checkout_billing', [ $this, 'print_probe' ] );

        $html = WcHooks::fire( 'woocommerce_checkout_billing' );
        $this->assertStringContainsString( 'PROBE', $html );
        $this->assertStringNotContainsString( 'woocommerce-billing-fields', $html );
        $this->assertNotFalse( has_action( 'woocommerce_checkout_billing', [ WC()->checkout(), 'checkout_form_billing' ] ) );

        $html = WcHooks::fire( 'woocommerce_before_checkout_form' );
        $this->assertStringNotContainsString( 'woocommerce-form-login', $html );
        $this->assertStringNotContainsString( 'checkout_coupon', $html );
        $this->assertStringNotContainsString( 'woocommerce-notices-wrapper', $html );
        $this->assertNotFalse( has_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' ) );
    }

    public function test_fire_ignores_hooks_outside_the_catalogue(): void {
        add_action( 'woocommerce_login_form', [ $this, 'print_probe' ] );

        $this->assertSame( '', WcHooks::fire( 'woocommerce_login_form' ) );
        $this->assertSame( '', WcHooks::fire( 'init' ) );
    }

    public function print_probe(): void {
        echo 'PROBE';
    }
}
