<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\BlockVisibility;
use YayCheckout\Tests\Support\LayoutFactory;

/** A malformed setting shows its block, and each rule matches exactly where the shopper expects it to. */
class BlockVisibilityTest extends WP_UnitTestCase {

    private const STATE = [
        'needs_shipping' => true,
        'has_coupon'     => false,
        'subtotal'       => 50.0,
        'logged_in'      => false,
    ];

    public function test_normalize_rejects_malformed_settings(): void {
        foreach ( [
            null,
            [],
            [
                'action' => 'show',
                'rules'  => [],
            ],
            [
                'action' => 'maybe',
                'rules'  => [ [ 'type' => 'logged_in' ] ],
            ],
            [
                'action' => 'show',
                'rules'  => [ [ 'type' => 'unknown' ] ],
            ],
            [
                'action' => 'show',
                'rules'  => [
                    [
                        'type' => 'step',
                        'step' => 0,
                    ],
                ],
            ],
            [
                'action' => 'show',
                'rules'  => [
                    [
                        'type'   => 'subtotal',
                        'op'     => 'gte',
                        'amount' => -1,
                    ],
                ],
            ],
            [
                'action' => 'show',
                'rules'  => [
                    [
                        'type'   => 'subtotal',
                        'op'     => 'eq',
                        'amount' => 5,
                    ],
                ],
            ],
            [
                'action' => 'show',
                'rules'  => [ [ 'type' => 'has_coupon' ], [ 'type' => 'has_coupon' ] ],
            ],
        ] as $value ) {
            $this->assertNull( BlockVisibility::normalize( $value ), wp_json_encode( $value ) );
        }
    }

    public function test_step_rules(): void {
        $last = BlockVisibility::normalize(
            $this->setting(
                'show',
                [
                    'type' => 'step',
                    'step' => 'last',
                ]
            )
        );
        $two  = BlockVisibility::normalize(
            $this->setting(
                'hide',
                [
                    'type' => 'step',
                    'step' => 2,
                ]
            )
        );

        $this->assertFalse( BlockVisibility::is_visible( $last, self::STATE, 1, 4 ) );
        $this->assertTrue( BlockVisibility::is_visible( $last, self::STATE, 4, 4 ) );
        $this->assertTrue( BlockVisibility::is_visible( $last, self::STATE, 1, 1 ) );  // One step: it is the last.
        $this->assertFalse( BlockVisibility::is_visible( $last, self::STATE, 1, 0 ) ); // No Steps block: never matches.
        $this->assertFalse( BlockVisibility::is_visible( $two, self::STATE, 2, 4 ) );
        $this->assertTrue( BlockVisibility::is_visible( $two, self::STATE, 3, 4 ) );
    }

    public function test_subtotal_boundary(): void {
        $gte = BlockVisibility::normalize(
            $this->setting(
                'show',
                [
                    'type'   => 'subtotal',
                    'op'     => 'gte',
                    'amount' => 50,
                ]
            )
        );
        $lt  = BlockVisibility::normalize(
            $this->setting(
                'show',
                [
                    'type'   => 'subtotal',
                    'op'     => 'lt',
                    'amount' => 50,
                ]
            )
        );

        $this->assertFalse( BlockVisibility::is_visible( $gte, [ 'subtotal' => 49.99 ] + self::STATE, 1, 0 ) );
        $this->assertTrue( BlockVisibility::is_visible( $gte, [ 'subtotal' => 50.0 ] + self::STATE, 1, 0 ) );
        $this->assertTrue( BlockVisibility::is_visible( $lt, [ 'subtotal' => 49.99 ] + self::STATE, 1, 0 ) );
        $this->assertFalse( BlockVisibility::is_visible( $lt, [ 'subtotal' => 50.0 ] + self::STATE, 1, 0 ) );
    }

    public function test_every_rule_must_match(): void {
        $both = BlockVisibility::normalize( $this->setting( 'show', [ 'type' => 'logged_in' ], [ 'type' => 'has_coupon' ] ) );

        $this->assertFalse( BlockVisibility::is_visible( $both, [ 'logged_in' => true ] + self::STATE, 1, 0 ) );
        $this->assertTrue(
            BlockVisibility::is_visible(
                $both,
                [
                    'logged_in'  => true,
                    'has_coupon' => true,
                ] + self::STATE,
                1,
                0
            )
        );
    }

    public function test_render_filter_marks_place_order_hidden_until_last_step(): void {
        $setting = '{"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":"last"}]}}';
        $steps   = '<!-- wp:yaycheckout/steps --><!-- wp:yaycheckout/step {"title":"A"} /--><!-- wp:yaycheckout/step {"title":"B"} /--><!-- /wp:yaycheckout/steps -->';
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout -->' . $steps . '<!-- wp:yaycheckout/email /--><!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/place-order ' . $setting . ' /--><!-- /wp:yaycheckout/checkout -->' );

        $html = do_blocks( '<!-- wp:yaycheckout/place-order ' . $setting . ' /-->' );

        $this->assertStringContainsString( 'data-yaycheckout-visibility=', $html );
        $this->assertStringContainsString( 'yaycheckout-hidden-by-visibility', $html );
    }

    public function test_render_filter_stands_down_without_layout_and_on_excluded_blocks(): void {
        $setting = '{"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"logged_in"}]}}';
        LayoutFactory::forget_served_layout();
        $this->assertStringNotContainsString( 'data-yaycheckout-visibility', do_blocks( '<!-- wp:paragraph ' . $setting . ' --><p>x</p><!-- /wp:paragraph -->' ) );

        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $this->assertStringNotContainsString( 'data-yaycheckout-visibility', do_blocks( '<!-- wp:yaycheckout/step ' . $setting . ' /-->' ) );
    }

    /** A setting with $action and the rules after it. */
    private function setting( string $action, array ...$rules ): array {
        return [
            'action' => $action,
            'rules'  => $rules,
        ];
    }
}
