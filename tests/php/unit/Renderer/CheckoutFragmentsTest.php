<?php
namespace YayTests\Unit\Renderer;

use Brain\Monkey\Functions;
use YayCheckout\Renderer\CheckoutFragments;
use YayTests\Unit\TestCase;

/**
 * @group baseline
 */
final class CheckoutFragmentsTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Functions\when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );
		Functions\when( 'sanitize_title' )->alias( 'strtolower' );
		Functions\when( '_wp_to_kebab_case' )->alias( static fn( $value ) => strtolower( str_replace( '_', '-', $value ) ) );
		Functions\when( 'sanitize_hex_color' )->alias( static fn( $value ) => preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $value ) ? $value : null );
	}

	private static function payment_style( array $style, array $attributes = [] ): string {
		return CheckoutFragments::payment_methods_style_attribute( array_merge( [ 'style' => $style ], $attributes ) );
	}

	public function test_prints_payment_vars_from_the_block_style() {
		$this->assertSame( '', self::payment_style( [] ) );
		$this->assertSame( '--yaycheckout-payment-radius:4px', self::payment_style( [ 'border' => [ 'radius' => 4 ] ] ) );
		$this->assertSame(
			'--yaycheckout-payment-bg:var(--wp--preset--color--primary);--yaycheckout-payment-border:#ff0000',
			self::payment_style(
				[
					'color'       => [ 'background' => 'var:preset|color|primary' ],
					'yaycheckout' => [ 'border' => '#ff0000' ],
				]
			)
		);
		$this->assertSame( 'color:var(--wp--preset--color--accent)', self::payment_style( [], [ 'textColor' => 'accent' ] ) );
	}

	public function test_drops_a_value_that_could_open_a_rule_or_comment() {
		foreach ( [ 'red}', 'a{b', '/*x', 'var:preset|color|', 'x=y' ] as $bad ) {
			$this->assertSame( '', CheckoutFragments::css_color( $bad ), $bad );
		}
	}

	public function test_falls_back_to_joined_for_an_unknown_layout() {
		$this->assertSame( 'joined', CheckoutFragments::payment_layout( [ 'layout' => 'nope' ] ) );
		$this->assertSame( 'joined', CheckoutFragments::payment_layout( [] ) );
		$this->assertSame( 'cards', CheckoutFragments::payment_layout( [ 'layout' => 'cards' ] ) );
	}

	public function test_clamps_thumbnail_sizes_and_maps_the_ratio() {
		$this->assertSame(
			[ '--yaycheckout-thumbnail-size:96px', '--yaycheckout-thumbnail-radius:0px', '--yaycheckout-thumbnail-ratio:3 / 4' ],
			CheckoutFragments::thumbnail_style_vars(
				[
					'thumbnailSize'   => 500,
					'thumbnailRadius' => -5,
					'thumbnailRatio'  => 'portrait',
				]
			)
		);
		$this->assertSame( [], CheckoutFragments::thumbnail_style_vars( [ 'thumbnailRatio' => 'square' ] ) );
	}

	/**
	 * @group B-1
	 */
	public function test_refuses_a_value_that_adds_a_declaration() {
		$this->assertSame( '', self::payment_style( [ 'border' => [ 'radius' => '4px;position:fixed' ] ] ) );
		$this->assertSame( '', CheckoutFragments::css_color( 'red;position:fixed' ) );
		$this->assertSame( '', CheckoutFragments::css_color( 'red\\3b position:fixed' ) );
		$this->assertSame( '--yaycheckout-payment-radius:0.5rem', self::payment_style( [ 'border' => [ 'radius' => '0.5rem' ] ] ) );
	}
}
