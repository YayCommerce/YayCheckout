<?php
namespace YayTests\Unit\Models;

use Brain\Monkey\Functions;
use YayCheckout\Models\Layout;
use YayTests\Unit\TestCase;

/**
 * @group baseline
 */
final class LayoutTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Functions\when( 'wc_get_price_decimals' )->justReturn( 2 );
		Functions\when( 'WC' )->justReturn(
			(object) [
				'countries' => new class() {
					public function get_countries() {
						return [
							'US' => 'United States',
							'VN' => 'Vietnam',
						];
					}
				},
			]
		);
	}

	private static function condition( string $type, string $operator, $value ): array {
		return [
			'type'     => $type,
			'operator' => $operator,
			'value'    => $value,
		];
	}

	public function test_refuses_rules_that_are_not_a_list_of_non_empty_groups() {
		$this->assertSame( [], Layout::sanitize_rules( [] ) );

		foreach ( [ 'x', [ 'a' => [] ], [ [] ] ] as $input ) {
			try {
				Layout::sanitize_rules( $input );
				$this->fail( 'Accepted ' . json_encode( $input ) );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertMatchesRegularExpression( '/must be a (non-empty )?list/', $e->getMessage() );
			}
		}
	}

	public function test_refuses_an_unknown_type_or_an_operator_the_type_does_not_take() {
		$this->expectExceptionMessage( 'Invalid operator in group 1, condition 2.' );

		Layout::sanitize_rules(
			[
				[
					self::condition( 'login_status', 'is', 'guest' ),
					self::condition( 'cart_quantity', 'contains_any', 3 ),
				],
			]
		);
	}

	public function test_dedupes_ids_as_ints_and_refuses_zero_or_too_many() {
		$clean = Layout::sanitize_rules( [ [ self::condition( 'product', 'contains_any', [ '5', 5, 7 ] ) ] ] );
		$this->assertSame( [ 5, 7 ], $clean[0][0]['value'] );

		foreach ( [ [ 0 ], [ '05' ], range( 1, 501 ) ] as $ids ) {
			try {
				Layout::sanitize_rules( [ [ self::condition( 'product', 'contains_any', $ids ) ] ] );
				$this->fail( 'Accepted ids ' . count( $ids ) );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'Invalid value in group 1, condition 1.', $e->getMessage() );
			}
		}
	}

	public function test_normalizes_amounts_counts_roles_and_countries() {
		$clean = Layout::sanitize_rules(
			[
				[
					self::condition( 'cart_subtotal', 'at_least', '19.999' ),
					self::condition( 'cart_quantity', 'less_than', 3.0 ),
					self::condition( 'customer_role', 'in', [ 'customer', 'customer' ] ),
					self::condition( 'billing_country', 'not_in', [ 'US', 'XX' ] ),
				],
			]
		);

		$this->assertSame( 20.0, $clean[0][0]['value'] );
		$this->assertSame( 3, $clean[0][1]['value'] );
		$this->assertSame( [ 'customer' ], $clean[0][2]['value'] );
		$this->assertSame( [ 'US' ], $clean[0][3]['value'] );

		foreach ( [ [ 'cart_subtotal', -1 ], [ 'cart_subtotal', '1e3' ], [ 'cart_quantity', 2.5 ], [ 'customer_role', [ 'a b' ] ] ] as $bad ) {
			$operator = 'customer_role' === $bad[0] ? 'in' : 'at_least';
			try {
				Layout::sanitize_rules( [ [ self::condition( $bad[0], $operator, $bad[1] ) ] ] );
				$this->fail( 'Accepted ' . $bad[0] );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'Invalid value in group 1, condition 1.', $e->getMessage() );
			}
		}
	}
}
