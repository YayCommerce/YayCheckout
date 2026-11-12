<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\BlockVisibility;
use YayCheckout\Renderer\LayoutShortcodes;
use YayCheckout\Tests\Support\LayoutFactory;

/** Shortcodes run in the layout's Shortcode and text blocks, as on any page, once, and never in a value the shopper typed. */
class LayoutShortcodesTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        add_shortcode( 'zz_probe', static fn() => 'SC-OK' );
    }

    public function tear_down(): void {
        remove_shortcode( 'zz_probe' );
        if ( function_exists( 'WC' ) && WC()->customer ) {
            WC()->customer->set_billing_first_name( '' );
        }
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    public function test_shortcodes_run_in_shortcode_and_text_blocks(): void {
        $html = LayoutShortcodes::render( "<!-- wp:shortcode -->\n[zz_probe]\n<!-- /wp:shortcode -->\n<!-- wp:paragraph --><p>P [zz_probe]</p><!-- /wp:paragraph -->" );

        $this->assertSame( 2, substr_count( $html, 'SC-OK' ) );
        $this->assertStringNotContainsString( '[zz_probe]', $html );
    }

    public function test_shortcodes_never_run_in_yaycheckout_blocks(): void {
        wc_load_cart();
        WC()->customer->set_billing_first_name( '[zz_probe]' );

        $html = LayoutShortcodes::render( '<!-- wp:yaycheckout/field {"fieldKey":"billing_first_name","type":"text","label":"First name"} /-->' );

        $this->assertStringContainsString( 'value="[zz_probe]"', $html );
        $this->assertStringNotContainsString( 'SC-OK', $html );
    }

    public function test_filter_is_removed_after_render(): void {
        LayoutShortcodes::render( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );

        $this->assertFalse( has_filter( 'render_block', [ LayoutShortcodes::class, 'run_in_block' ] ) );
    }

    public function test_nested_list_item_runs_once(): void {
        $html = LayoutShortcodes::render( '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>a<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>[[zz_probe]]</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --></ul><!-- /wp:list -->' );

        $this->assertStringContainsString( '[zz_probe]', $html );
        $this->assertStringNotContainsString( 'SC-OK', $html );
    }

    public function test_hide_rule_marks_the_shortcode_wrapper(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        wp_set_current_user( self::factory()->user->create() );
        add_shortcode( 'zz_block', static fn() => '<div class="zz-out">x</div>' );

        $html = LayoutShortcodes::render( '<!-- wp:shortcode {"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"logged_in"}]}} -->' . "\n[zz_block]\n" . '<!-- /wp:shortcode -->' );
        remove_shortcode( 'zz_block' );

        // The processor writes new attributes right after the tag name, so match them in any order.
        $this->assertMatchesRegularExpression( '#<div [^>]*\bwp-block-shortcode\b[^>]*>\s*<div class="zz-out">#', $html );
        $this->assertMatchesRegularExpression( '#<div [^>]*\b' . BlockVisibility::HIDDEN_CLASS . '\b[^>]*>\s*<div class="zz-out">#', $html );
    }

    public function test_a_null_from_another_filter_does_not_fatal(): void {
        $null = static fn() => null;
        add_filter( 'render_block', $null, 8 );

        $html = LayoutShortcodes::render( '<!-- wp:paragraph --><p>[zz_probe]</p><!-- /wp:paragraph -->' );
        remove_filter( 'render_block', $null, 8 );

        $this->assertSame( '', trim( $html ) );
    }

    public function test_a_live_value_of_a_shopper_field_stays_text(): void {
        $page = self::factory()->post->create( [ 'post_type' => 'page' ] );
        update_option( 'woocommerce_checkout_page_id', $page );
        // A live value shows only a field inside Billing or Shipping (ShowableFields).
        $markup = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/billing --><!-- wp:yaycheckout/field {"fieldKey":"billing_first_name","type":"text","label":"First name"} /--><!-- /wp:yaycheckout/billing -->'
            . '<!-- wp:paragraph --><p>Hi <span class="yaycheckout-live-value" data-yaycheckout-value="field:billing_first_name"></span></p><!-- /wp:paragraph -->'
            . '<!-- /wp:yaycheckout/checkout -->';
        LayoutFactory::serve( $markup );
        $this->go_to( get_permalink( $page ) );
        wc_load_cart();
        WC()->customer->set_billing_first_name( '[zz_probe]' );

        $html = LayoutShortcodes::render( $markup );

        $this->assertMatchesRegularExpression( '#yaycheckout-live-value[^>]*>\[zz_probe\]</span>#', $html );
        $this->assertStringNotContainsString( 'SC-OK', $html );
    }
}
