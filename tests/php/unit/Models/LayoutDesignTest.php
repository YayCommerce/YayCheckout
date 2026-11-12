<?php
namespace YayTests\Unit\Models;

use YayCheckout\Models\LayoutDesign;
use YayTests\Unit\TestCase;

require_once dirname( __DIR__ ) . '/stubs/WP_Error.php';

/**
 * @group baseline
 */
final class LayoutDesignTest extends TestCase {

	private static function colour( string $key, $value ): string {
		return LayoutDesign::from_meta( [ 'colours' => [ $key => $value ] ] )->to_meta()['colours'][ $key ];
	}

	public function test_keeps_hex_and_theme_preset_colours_only() {
		$this->assertSame( '#12AB34', self::colour( 'primary', '#12AB34' ) );
		$this->assertSame( 'var(--wp--preset--color--accent)', self::colour( 'primary', 'var(--wp--preset--color--accent)' ) );

		foreach ( [ '#fff', 'red', '#12345678', 'var(--wp--preset--color--yaycheckout-primary)', '#12AB34;}', 42 ] as $bad ) {
			$this->assertSame( '', self::colour( 'primary', $bad ), (string) json_encode( $bad ) );
		}
	}

	public function test_keeps_a_gradient_background_that_cannot_end_its_declaration() {
		$this->assertSame( 'linear-gradient(red, blue)', self::colour( 'background', 'linear-gradient(red, blue)' ) );
		$this->assertSame( 'var(--wp--preset--gradient--vivid)', self::colour( 'background', 'var(--wp--preset--gradient--vivid)' ) );

		foreach ( [ 'linear-gradient(red;x)', 'linear-gradient(red{)', 'linear-gradient(red/*)', "linear-gradient('a')", 'linear-gradient(</style>)' ] as $bad ) {
			$this->assertSame( '', self::colour( 'background', $bad ), $bad );
		}
		$this->assertFalse( LayoutDesign::is_gradient( 'linear-gradient(' . str_repeat( 'a', 1000 ) . ')' ) );
		$this->assertSame( '', self::colour( 'primary', 'linear-gradient(red, blue)' ) );
	}

	public function test_projects_every_section_and_reads_legacy_input_keys() {
		$meta = LayoutDesign::from_meta(
			[
				'typography' => [ 'base_size' => 25 ],
				'inputs'     => [
					'radius' => 0,
					'size'   => 'huge',
					'focus'  => '#000000',
					'text'   => '#111111',
				],
				'unknown'    => [ 'x' => 1 ],
			]
		)->to_meta();

		$this->assertSame( [ 'colours', 'typography', 'inputs', 'checkboxes', 'radios', 'help_text', 'buttons', 'notices', 'custom_css' ], array_keys( $meta ) );
		$this->assertNull( $meta['typography']['base_size'] );
		$this->assertSame( 0, $meta['inputs']['radius'] );
		$this->assertSame( '', $meta['inputs']['size'] );
		$this->assertSame( '#000000', $meta['inputs']['focus_border'] );
		$this->assertSame( '#111111', $meta['inputs']['text_color'] );
		$this->assertSame( LayoutDesign::from_meta( [] )->to_meta(), LayoutDesign::from_meta( 'garbage' )->to_meta() );
	}

	public function test_refuses_custom_css_that_closes_the_style_element_or_is_too_large() {
		$this->assertSame( 'a{color:red}', LayoutDesign::sanitize_custom_css( 'a{color:red}' ) );
		$this->assertSame( 50000, strlen( LayoutDesign::sanitize_custom_css( str_repeat( 'a', 50000 ) ) ) );

		foreach ( [ 'a</STYLE>', '<script>', "a\0b", str_repeat( 'a', 50001 ) ] as $bad ) {
			$this->assertInstanceOf( \WP_Error::class, LayoutDesign::sanitize_custom_css( $bad ) );
		}
	}
}
