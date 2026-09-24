<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Services\RequiredIndicator;

/**
 * The * / (optional) label suffix: swapped into WooCommerce's field markup,
 * printed by block fallbacks, and handed to the storefront script.
 */
class RequiredIndicatorTest extends WP_UnitTestCase {

    private static function wc_field( string $key, bool $required ): string {
        return woocommerce_form_field(
            $key,
            [
                'label'    => 'Label',
                'required' => $required,
                'return'   => true,
            ]
        );
    }

    public function test_swaps_required_mark(): void {
        $html = RequiredIndicator::swap_in(
            self::wc_field( 'billing_company', true ),
            'billing_company',
            [ 'required' => true ],
            [
                'billing_company' => [
                    'fieldKey'     => 'billing_company',
                    'requiredMark' => '!',
                ],
            ]
        );

        $this->assertStringContainsString( '<span class="required" aria-hidden="true">!</span>', $html );
        $this->assertStringNotContainsString( '>*</span>', $html );
    }

    public function test_swaps_optional_text(): void {
        $html = RequiredIndicator::swap_in(
            self::wc_field( 'billing_company', false ),
            'billing_company',
            [ 'required' => false ],
            [
                'billing_company' => [
                    'fieldKey'     => 'billing_company',
                    'optionalText' => 'if any',
                ],
            ]
        );

        $this->assertStringContainsString( '<span class="optional">(if any)</span>', $html );
    }

    public function test_escapes_custom_strings(): void {
        $html = RequiredIndicator::swap_in(
            self::wc_field( 'billing_company', true ),
            'billing_company',
            [ 'required' => true ],
            [
                'billing_company' => [
                    'fieldKey'     => 'billing_company',
                    'requiredMark' => '<b>',
                ],
            ]
        );

        $this->assertStringContainsString( '>&lt;b&gt;</span>', $html );
    }

    /** Empty strings, unknown keys and non-string input leave WooCommerce's markup alone. */
    public function test_leaves_stock_markup_alone(): void {
        $required = self::wc_field( 'billing_company', true );
        $optional = self::wc_field( 'billing_company', false );
        $layout   = [
            'billing_company' => [
                'fieldKey'     => 'billing_company',
                'requiredMark' => '',
                'optionalText' => '',
            ],
        ];

        $this->assertSame( $required, RequiredIndicator::swap_in( $required, 'billing_company', [ 'required' => true ], $layout ) );
        $this->assertSame( $optional, RequiredIndicator::swap_in( $optional, 'billing_company', [ 'required' => false ], $layout ) );
        $this->assertSame( $required, RequiredIndicator::swap_in( $required, 'billing_city', [ 'required' => true ], [ 'billing_company' => [ 'requiredMark' => '!' ] ] ) );
        $this->assertNull( RequiredIndicator::swap_in( null, 'billing_company', [], $layout ) );
    }

    /** Raw block attributes: blank or whitespace strings fall back to * and (optional). */
    public function test_fallback_html(): void {
        $this->assertSame( '&nbsp;<span class="required" aria-hidden="true">*</span>', RequiredIndicator::html( true, [] ) );
        $this->assertSame( '&nbsp;<span class="optional">(optional)</span>', RequiredIndicator::html( false, [ 'optionalText' => '   ' ] ) );
        $this->assertSame( '&nbsp;<span class="required" aria-hidden="true">!</span>', RequiredIndicator::html( true, [ 'requiredMark' => ' ! ' ] ) );
        $this->assertSame( '&nbsp;<span class="optional">(if any)</span>', RequiredIndicator::html( false, [ 'optionalText' => 'if any' ] ) );
        $this->assertSame( '&nbsp;<span class="required" aria-hidden="true">&lt;b&gt;</span>', RequiredIndicator::html( true, [ 'requiredMark' => '<b>' ] ) );
    }

    /** Email is required whatever the layout says; strings pass through. */
    public function test_script_payload(): void {
        $layout = [
            'billing_email'   => [
                'fieldKey'     => 'billing_email',
                'type'         => 'email',
                'required'     => false,
                'requiredMark' => '!',
                'optionalText' => '',
            ],
            'billing_company' => [
                'fieldKey'     => 'billing_company',
                'required'     => false,
                'requiredMark' => '',
                'optionalText' => 'if any',
            ],
            'gift'            => [
                'fieldKey'     => 'gift',
                'required'     => true,
                'requiredMark' => '',
                'optionalText' => '',
            ],
        ];

        $this->assertSame(
            [
                'billing_email'   => [
                    'required'     => true,
                    'requiredMark' => '!',
                    'optionalText' => '',
                ],
                'billing_company' => [
                    'required'     => false,
                    'requiredMark' => '',
                    'optionalText' => 'if any',
                ],
                'gift'            => [
                    'required'     => true,
                    'requiredMark' => '',
                    'optionalText' => '',
                ],
            ],
            RequiredIndicator::script_payload( $layout )
        );
    }
}
