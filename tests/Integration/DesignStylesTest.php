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

    public function test_defaults_print_nothing(): void {
        $this->assertSame( '', $this->css( [] ) );
    }

    public function test_enabled_colours_print_every_colour(): void {
        $css = $this->css( [ 'colours' => [ 'enabled' => true ] ] );

        $this->assertStringContainsString( '--yaycheckout-color-primary: #3858e9;', $css );
        $this->assertStringContainsString( '--yaycheckout-color-text: #171719;', $css );
        $this->assertStringContainsString( '--yaycheckout-color-background: #ffffff;', $css );
        $this->assertStringContainsString( '--yaycheckout-color-border: #e4e4e7;', $css );
    }

    public function test_theme_font_prints_size_only(): void {
        $css = $this->css( [ 'typography' => [ 'enabled' => true ] ] );

        $this->assertStringContainsString( '--yaycheckout-font-size-base: 16px;', $css );
        $this->assertStringNotContainsString( '--yaycheckout-font-family', $css );
    }

    public function test_picked_font_prints_family_and_size(): void {
        $css = $this->css(
            [
                'typography' => [
                    'enabled'     => true,
                    'font_family' => 'inter',
                    'base_size'   => 18,
                ],
            ]
        );

        $this->assertStringContainsString( '--yaycheckout-font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;', $css );
        $this->assertStringContainsString( '--yaycheckout-font-size-base: 18px;', $css );
    }

    public function test_enabled_inputs_print_without_buttons(): void {
        $css = $this->css(
            [
                'inputs' => [
                    'enabled' => true,
                    'size'    => 'large',
                ],
            ]
        );

        $this->assertStringContainsString( '--yaycheckout-input-height: 48px;', $css );
        $this->assertStringContainsString( '--yaycheckout-input-radius: 10px;', $css );
        $this->assertStringNotContainsString( '--yaycheckout-button-', $css );
    }

    public function test_spacing_scale_prints_its_step(): void {
        $css = $this->css(
            [
                'spacing' => [
                    'enabled' => true,
                    'scale'   => 1.5,
                ],
            ]
        );

        $this->assertStringContainsString( '--yaycheckout-spacing-scale: 1.5;', $css );
        $this->assertStringContainsString( '--yaycheckout-radius: 10px;', $css );
    }

    public function test_custom_css_follows_the_variables(): void {
        $css = ( new DesignStyles() )->css(
            Design::from_option(
                [
                    'tokens'     => [ 'spacing' => [ 'enabled' => true ] ],
                    'custom_css' => '.yaycheckout-checkout { color: red; }',
                ]
            )
        );

        $this->assertGreaterThan( strpos( $css, '}' ), strpos( $css, '.yaycheckout-checkout' ) );
    }

    public function test_custom_css_that_escapes_the_style_tag_is_dropped(): void {
        $css = ( new DesignStyles() )->css( Design::from_option( [ 'custom_css' => '</style><script>alert(1)</script>' ] ) );

        $this->assertSame( '', $css );
    }

    public function test_taken_over_checkout_enqueues_the_variables(): void {
        update_option( Design::OPTION_DESIGN, [ 'tokens' => [ 'spacing' => [ 'enabled' => true ] ] ] );
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        CheckoutPage::get_instance()->enqueue_scripts();

        $this->assertTrue( wp_style_is( ScriptName::STYLE_CHECKOUT ) );
        $this->assertStringContainsString(
            '--yaycheckout-spacing-scale',
            implode( '', wp_styles()->get_data( ScriptName::STYLE_CHECKOUT, 'after' ) )
        );
    }

    public function test_classes_name_only_the_sections_that_are_on(): void {
        $this->assertSame( [], DesignStyles::classes( Design::from_option( [] ) ) );

        $design = Design::from_option(
            [
                'tokens' => [
                    'colours' => [ 'enabled' => true ],
                    'buttons' => [
                        'enabled' => true,
                        'style'   => 'outline',
                    ],
                    'inputs'  => [ 'style' => 'underline' ],
                ],
            ]
        );

        $this->assertSame(
            [ 'yaycheckout-design-colours', 'yaycheckout-design-buttons', 'yaycheckout-design-buttons-outline' ],
            DesignStyles::classes( $design )
        );
    }

    public function test_body_gets_the_classes_of_the_sections_that_are_on(): void {
        update_option( Design::OPTION_DESIGN, [ 'tokens' => [ 'colours' => [ 'enabled' => true ] ] ] );

        $this->assertSame( [ 'theme-class', 'yaycheckout-design-colours' ], CheckoutPage::get_instance()->body_class( [ 'theme-class' ] ) );
    }

    public function test_taken_over_checkout_enqueues_the_stylesheet(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );

        CheckoutPage::get_instance()->enqueue_scripts();

        $this->assertStringEndsWith( 'assets/css/checkout.css', wp_styles()->registered[ ScriptName::STYLE_CHECKOUT ]->src );
    }

    private function css( array $tokens ): string {
        return ( new DesignStyles() )->css( Design::from_option( [ 'tokens' => $tokens ] ) );
    }
}
