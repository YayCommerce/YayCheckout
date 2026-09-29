<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Models\Design;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Renderer\DesignStyles;
use YayCheckout\Tests\Support\LayoutFactory;

/** Design tokens printed as CSS custom properties on the storefront. */
class DesignStylesTest extends WP_UnitTestCase {

    public function tear_down(): void {
        wp_deregister_style( ScriptName::STYLE_CHECKOUT );
        LayoutFactory::forget_served_layout();

        parent::tear_down();
    }

    public function test_defaults_print_every_token_but_the_font(): void {
        $css = $this->css( [] );

        $this->assertStringContainsString( '--yaycheckout-color-primary: #3858e9;', $css );
        $this->assertStringContainsString( '--yaycheckout-color-text: #171719;', $css );
        $this->assertStringContainsString( '--yaycheckout-color-background: #ffffff;', $css );
        $this->assertStringContainsString( '--yaycheckout-color-border: #e4e4e7;', $css );
        $this->assertStringContainsString( '--yaycheckout-spacing-scale: 1;', $css );
        $this->assertStringContainsString( '--yaycheckout-radius: 10px;', $css );
        $this->assertStringContainsString( '--yaycheckout-button-radius: 10px;', $css );
        $this->assertStringContainsString( '--yaycheckout-input-radius: 10px;', $css );
        $this->assertStringContainsString( '--yaycheckout-input-height: 40px;', $css );
        $this->assertStringNotContainsString( '--yaycheckout-font', $css );
    }

    public function test_theme_font_prints_size_only(): void {
        $css = $this->css( [ 'typography' => [ 'inherit' => false ] ] );

        $this->assertStringContainsString( '--yaycheckout-font-size-base: 16px;', $css );
        $this->assertStringNotContainsString( '--yaycheckout-font-family', $css );
    }

    public function test_picked_font_prints_family_and_size(): void {
        $css = $this->css(
            [
                'typography' => [
                    'inherit'     => false,
                    'font_family' => 'inter',
                    'base_size'   => 18,
                ],
            ]
        );

        $this->assertStringContainsString( '--yaycheckout-font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;', $css );
        $this->assertStringContainsString( '--yaycheckout-font-size-base: 18px;', $css );
    }

    public function test_steps_map_to_their_values(): void {
        $css = $this->css(
            [
                'spacing' => [ 'scale' => 1.5 ],
                'inputs'  => [ 'size' => 'large' ],
            ]
        );

        $this->assertStringContainsString( '--yaycheckout-spacing-scale: 1.5;', $css );
        $this->assertStringContainsString( '--yaycheckout-input-height: 48px;', $css );
    }

    public function test_custom_css_follows_the_variables(): void {
        $css = ( new DesignStyles() )->css( Design::from_option( [ 'custom_css' => '.yaycheckout-checkout { color: red; }' ] ) );

        $this->assertGreaterThan( strpos( $css, '}' ), strpos( $css, '.yaycheckout-checkout' ) );
    }

    public function test_custom_css_that_escapes_the_style_tag_is_dropped(): void {
        $css = ( new DesignStyles() )->css( Design::from_option( [ 'custom_css' => '</style><script>alert(1)</script>' ] ) );

        $this->assertStringNotContainsString( '<script', $css );
        $this->assertStringContainsString( '--yaycheckout-color-primary', $css );
    }

    public function test_taken_over_checkout_enqueues_the_variables(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        CheckoutPage::get_instance()->enqueue_scripts();

        $this->assertTrue( wp_style_is( ScriptName::STYLE_CHECKOUT ) );
        $this->assertStringContainsString(
            '--yaycheckout-color-primary',
            implode( '', wp_styles()->get_data( ScriptName::STYLE_CHECKOUT, 'after' ) )
        );
    }

    private function css( array $tokens ): string {
        return ( new DesignStyles() )->css( Design::from_option( [ 'tokens' => $tokens ] ) );
    }
}
