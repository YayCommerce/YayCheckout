<?php
namespace YayCheckout\Tests\Integration\Pro;

use WP_UnitTestCase;
use YayCheckout\Enums\ConditionType;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\Plan;
use YayCheckout\Tests\Support\LayoutFactory;

/** P-1: the merchant's priority decides which of several matching active layouts the storefront serves. */
class LayoutPriorityTest extends WP_UnitTestCase {

    private LayoutRepository $layouts;

    public function set_up(): void {
        parent::set_up();

        if ( ! Plan::is_pro() ) {
            $this->markTestSkipped( 'Layout priority is Pro.' );
        }

        $this->layouts = new LayoutRepository();
        delete_option( Layout::OPTION_ACTIVE );
    }

    /** Two active layouts, $a activated first, so $b starts on top. Both match a cart of 120. */
    private function two_matching(): array {
        $a = LayoutFactory::create_draft( [ 'post_title' => 'A' ] );
        $b = LayoutFactory::create_draft( [ 'post_title' => 'B' ] );

        $this->layouts->set_rules(
            $a,
            [
                [
                    [
                        'type'     => ConditionType::CART_SUBTOTAL,
                        'operator' => ConditionType::AT_LEAST,
                        'value'    => 100,
                    ],
                ],
            ]
        );
        $this->layouts->activate( $a );
        $this->layouts->activate( $b );

        return [ $a, $b ];
    }

    private static function cart_of( float $subtotal ): array {
        return [
            ConditionType::CUSTOMER_ROLE => [],
            ConditionType::LOGIN_STATUS  => ConditionType::GUEST,
            ConditionType::DEVICE        => ConditionType::DESKTOP,
            ConditionType::CART_ITEMS    => [],
            ConditionType::CART_SUBTOTAL => $subtotal,
            ConditionType::CART_QUANTITY => 1,
        ];
    }

    private function priority_ids(): array {
        return array_map( static fn( Layout $layout ) => $layout->id, $this->layouts->priority() );
    }

    public function test_priority_order_decides_which_match_is_served(): void {
        [ $a, $b ] = $this->two_matching();

        $this->assertSame( [ $b, $a ], $this->priority_ids() );
        $this->assertSame( $b, $this->layouts->active( self::cart_of( 120 ) )->id );

        $this->layouts->set_priority( [ $a, $b ] );

        $this->assertSame( [ $a, $b ], $this->priority_ids() );
        $this->assertSame( $a, $this->layouts->active( self::cart_of( 120 ) )->id );
        $this->assertSame( $b, $this->layouts->active( self::cart_of( 80 ) )->id, 'A cart below the threshold falls through to B.' );
    }

    public function test_set_priority_refuses_a_stale_list(): void {
        [ $a, $b ] = $this->two_matching();
        $stored    = get_option( Layout::OPTION_ACTIVE );
        $other     = LayoutFactory::create_draft();

        foreach ( [ [ $a ], [ $a, $b, $other ], [ $a, $a ], [ $a, $b, $a ], [] ] as $ids ) {
            try {
                $this->layouts->set_priority( $ids );
                $this->fail( 'Accepted ' . wp_json_encode( $ids ) . '.' );
            } catch ( \InvalidArgumentException $ex ) {
                $this->assertSame( $stored, get_option( Layout::OPTION_ACTIVE ) );
            }
        }
    }

    public function test_priority_route_saves_and_needs_manage_woocommerce(): void {
        [ $a, $b ] = $this->two_matching();
        LayoutFactory::as_shop_manager();

        $read = rest_do_request( new \WP_REST_Request( 'GET', '/yaycheckout/v1/layouts/priority' ) );
        $this->assertSame( 200, $read->get_status() );
        $this->assertSame( [ $b, $a ], wp_list_pluck( $read->get_data()['layouts'], 'id' ) );

        $saved = $this->put_priority( [ 'ids' => [ $a, $b ] ] );
        $this->assertSame( 200, $saved->get_status() );
        $this->assertSame( [ $a, $b ], wp_list_pluck( $saved->get_data()['layouts'], 'id' ) );
        $this->assertSame( [ $a, $b ], $this->priority_ids() );

        $this->assertSame( 409, $this->put_priority( [ 'ids' => [ $a ] ] )->get_status() );
        $this->assertSame( 400, $this->put_priority( [ 'ids' => [ (string) $a, (string) $b ] ] )->get_status() );
        $this->assertSame( 400, $this->put_priority( [] )->get_status() );
        $this->assertSame( [ $a, $b ], $this->priority_ids() );

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
        $this->assertSame( 403, $this->put_priority( [ 'ids' => [ $b, $a ] ] )->get_status() );
        $this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/yaycheckout/v1/layouts/priority' ) )->get_status() );
        $this->assertSame( [ $a, $b ], $this->priority_ids() );
    }

    private function put_priority( array $body ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'PUT', '/yaycheckout/v1/layouts/priority' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $body ) );

        return rest_do_request( $request );
    }

    public function test_reactivating_keeps_the_priority(): void {
        [ $a, $b ] = $this->two_matching();
        $this->layouts->set_priority( [ $a, $b ] );

        $this->layouts->activate( $b );
        $this->assertSame( [ $a, $b ], $this->priority_ids(), 'Activating an active layout leaves it in place.' );

        $c = LayoutFactory::create_draft();
        $this->layouts->activate( $c );
        $this->assertSame( [ $c, $a, $b ], $this->priority_ids(), 'A newly activated layout goes to the top.' );
    }
}
