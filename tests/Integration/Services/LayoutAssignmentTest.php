<?php
namespace YayCheckout\Tests\Integration\Services;

use WP_UnitTestCase;
use YayCheckout\Enums\ConditionType;
use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutAssignment;

/** Cart conditions pick the layout a shopper's cart calls for, and stay undecided until the cart is known. */
class LayoutAssignmentTest extends WP_UnitTestCase {

    private const SHOPPER = [
        ConditionType::CUSTOMER_ROLE => [ 'customer' ],
        ConditionType::LOGIN_STATUS  => ConditionType::LOGGED_IN,
        ConditionType::DEVICE        => ConditionType::DESKTOP,
    ];

    private static function layout( int $id, array $rules ): Layout {
        $layout        = new Layout();
        $layout->id    = $id;
        $layout->rules = $rules;

        return $layout;
    }

    private static function condition( string $type, string $operator, array $value ): array {
        return [
            'type'     => $type,
            'operator' => $operator,
            'value'    => $value,
        ];
    }

    /** @param array<int, array{product: int, categories: int[]}> $items */
    private static function context( array $items ): array {
        return self::SHOPPER + [ ConditionType::CART_ITEMS => $items ];
    }

    private static function item( int $product, array $categories = [] ): array {
        return [
            'product'    => $product,
            'categories' => $categories,
        ];
    }

    private function assert_matches( bool $expected, array $condition, array $items ): void {
        $this->assertSame( $expected, LayoutAssignment::matches( [ [ $condition ] ], self::context( $items ) ) );
    }

    public function test_product_operators(): void {
        $cart = [ self::item( 10 ), self::item( 20 ) ];

        $this->assert_matches( true, self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ANY, [ 10 ] ), $cart );
        $this->assert_matches( false, self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ANY, [ 30 ] ), $cart );
        $this->assert_matches( false, self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ALL, [ 10 ] ), $cart );
        $this->assert_matches( true, self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ALL, [ 10, 20, 30 ] ), $cart );
        $this->assert_matches( false, self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_NONE, [ 20 ] ), $cart );
        $this->assert_matches( true, self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_NONE, [ 30 ] ), $cart );
    }

    public function test_category_operators(): void {
        $cart = [ self::item( 10, [ 5, 1 ] ), self::item( 20, [ 7 ] ) ];

        $this->assert_matches( true, self::condition( ConditionType::PRODUCT_CATEGORY, ConditionType::CONTAINS_ANY, [ 1 ] ), $cart );
        $this->assert_matches( false, self::condition( ConditionType::PRODUCT_CATEGORY, ConditionType::CONTAINS_ALL, [ 1 ] ), $cart );
        $this->assert_matches( true, self::condition( ConditionType::PRODUCT_CATEGORY, ConditionType::CONTAINS_ALL, [ 1, 7 ] ), $cart );
        $this->assert_matches( true, self::condition( ConditionType::PRODUCT_CATEGORY, ConditionType::CONTAINS_NONE, [ 9 ] ), $cart );
        $this->assert_matches( false, self::condition( ConditionType::PRODUCT_CATEGORY, ConditionType::CONTAINS_NONE, [ 5 ] ), $cart );
    }

    /** "Only these" must not hold for an empty cart, or every empty cart would get that layout. */
    public function test_empty_cart_contains_none_and_not_only(): void {
        foreach ( [ ConditionType::PRODUCT, ConditionType::PRODUCT_CATEGORY ] as $type ) {
            $this->assert_matches( false, self::condition( $type, ConditionType::CONTAINS_ANY, [ 1 ] ), [] );
            $this->assert_matches( false, self::condition( $type, ConditionType::CONTAINS_ALL, [ 1 ] ), [] );
            $this->assert_matches( true, self::condition( $type, ConditionType::CONTAINS_NONE, [ 1 ] ), [] );
        }
    }

    public function test_cart_condition_ands_and_ors_with_shopper_conditions(): void {
        $wholesale = self::condition( ConditionType::CUSTOMER_ROLE, ConditionType::IN, [ 'wholesale' ] );
        $product   = self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ANY, [ 10 ] );
        $cart      = [ self::item( 10 ) ];

        $this->assertFalse( LayoutAssignment::matches( [ [ $wholesale, $product ] ], self::context( $cart ) ) );
        $this->assertTrue( LayoutAssignment::matches( [ [ $wholesale ], [ $product ] ], self::context( $cart ) ) );
    }

    /** Before the cart loads, a cart condition neither matches nor rules a layout out, so pick() passes over it. */
    public function test_unknown_cart_never_picks(): void {
        $cart_only = self::layout( 1, [ [ self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_NONE, [ 10 ] ) ] ] );
        $everyone  = self::layout( 2, [] );

        $this->assertSame( 2, LayoutAssignment::pick( [ $cart_only, $everyone ], self::SHOPPER )->id );
        $this->assertSame( 1, LayoutAssignment::pick( [ $cart_only, $everyone ], self::context( [] ) )->id );
    }

    public function test_outlook_before_the_cart_loads(): void {
        $cart_only = self::layout( 1, [ [ self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ANY, [ 10 ] ) ] ] );
        $guests    = self::layout(
            2,
            [
                [
                    [
                        'type'     => ConditionType::LOGIN_STATUS,
                        'operator' => ConditionType::IS,
                        'value'    => ConditionType::GUEST,
                    ],
                    self::condition( ConditionType::PRODUCT, ConditionType::CONTAINS_ANY, [ 10 ] ),
                ],
            ]
        );

        $this->assertSame( LayoutAssignment::OUTLOOK_MAYBE, LayoutAssignment::outlook( [ $cart_only ], self::SHOPPER ) );
        // The shopper is logged in, so the guest group is false whatever the cart holds.
        $this->assertSame( LayoutAssignment::OUTLOOK_NO, LayoutAssignment::outlook( [ $guests ], self::SHOPPER ) );
        $this->assertSame( LayoutAssignment::OUTLOOK_YES, LayoutAssignment::outlook( [ $cart_only, self::layout( 3, [] ) ], self::SHOPPER ) );
        $this->assertSame( LayoutAssignment::OUTLOOK_NO, LayoutAssignment::outlook( [], self::SHOPPER ) );
    }

    public function test_unknown_type_never_matches(): void {
        $this->assert_matches( false, self::condition( 'cart_weight', ConditionType::CONTAINS_NONE, [ 1 ] ), [] );
    }
}
