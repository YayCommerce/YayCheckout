<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Design;

/** Models\Design: which sections each built-in preset turns on. */
class DesignTest extends WP_UnitTestCase {

    private const TOGGLEABLE = [ 'colours', 'typography', 'spacing', 'buttons', 'inputs' ];

    public function test_each_preset_enables_its_sections(): void {
        $expected = [
            'default' => [],
            'dark'    => [ 'colours' ],
            'b2b'     => self::TOGGLEABLE,
        ];

        foreach ( Design::presets() as $preset ) {
            $enabled = array_values( array_filter( self::TOGGLEABLE, fn( string $section ) => $preset['tokens'][ $section ]['enabled'] ) );

            $this->assertSame( $expected[ $preset['id'] ], $enabled, $preset['id'] );
        }
    }

    public function test_preset_saved_before_enabled_existed_keeps_its_design(): void {
        $design = Design::from_option(
            [
                'presets' => [
                    [
                        'id'     => 'mine',
                        'name'   => 'Mine',
                        'tokens' => [
                            'colours'    => [ 'primary' => '#ff0000' ],
                            'typography' => [ 'inherit' => true ],
                            'spacing'    => [ 'radius' => 4 ],
                            'buttons'    => [ 'style' => 'outline' ],
                            'inputs'     => [ 'size' => 'large' ],
                        ],
                    ],
                ],
            ]
        );
        $tokens = end( $design->presets )['tokens'];

        $enabled = array_values( array_filter( self::TOGGLEABLE, fn( string $section ) => $tokens[ $section ]['enabled'] ) );

        $this->assertSame( [ 'colours', 'spacing', 'buttons', 'inputs' ], $enabled );
        $this->assertSame( '#ff0000', $tokens['colours']['primary'] );
    }

    public function test_legacy_preset_with_its_own_font_turns_typography_on(): void {
        $design = Design::from_option(
            [
                'presets' => [
                    [
                        'id'     => 'mine',
                        'name'   => 'Mine',
                        'tokens' => [ 'typography' => [ 'inherit' => false ] ],
                    ],
                ],
            ]
        );

        $this->assertTrue( end( $design->presets )['tokens']['typography']['enabled'] );
    }
}
