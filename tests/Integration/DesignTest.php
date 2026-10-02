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
}
