<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Services\LayoutAssignment;
use YayCheckout\Support\Plan;
use YayCheckout\Tests\Support\LayoutFactory;

/** Pins which layout the checkout serves once rules are set, and that the pick stays put for AJAX. */
class LayoutAssignmentTest extends WP_UnitTestCase {

    private const CONTEXT = [
        'country'     => 'DE',
        'roles'       => [ 'customer' ],
        'product_ids' => [ 10, 11 ],
        'device'      => 'mobile',
    ];

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        WC()->session->set( CheckoutPage::SESSION_PINNED, null );

        // Only a load of the checkout page pins.
        $page = self::factory()->post->create( [ 'post_type' => 'page' ] );
        update_option( 'woocommerce_checkout_page_id', $page );
        $this->go_to( get_permalink( $page ) );
    }

    public function tear_down(): void {
        remove_all_filters( 'wp_doing_ajax' );
        unset( $_SERVER['REQUEST_METHOD'] );
        parent::tear_down();
    }

    private static function rule( string $type, string $operator, $value ): array {
        return [
            [
                [
                    'type'     => $type,
                    'operator' => $operator,
                    'value'    => $value,
                ],
            ],
        ];
    }

    /** @dataProvider conditions */
    public function test_condition( array $rules, bool $expected ): void {
        $this->assertSame( $expected, LayoutAssignment::matches( $rules, self::CONTEXT ) );
    }

    public function conditions(): array {
        return [
            'country in'                 => [ self::rule( 'country', 'in', [ 'AT', 'DE' ] ), true ],
            'country not in'             => [ self::rule( 'country', 'not_in', [ 'DE' ] ), false ],
            'role in'                    => [ self::rule( 'customer_role', 'in', [ 'customer' ] ), true ],
            'role not in'                => [ self::rule( 'customer_role', 'not_in', [ 'wholesale' ] ), true ],
            'variation id in cart'       => [ self::rule( 'product_in_cart', 'in', [ 11 ] ), true ],
            'product not in cart'        => [ self::rule( 'product_in_cart', 'not_in', [ 99 ] ), true ],
            'device is'                  => [ self::rule( 'device', 'is', 'mobile' ), true ],
            'device is not'              => [ self::rule( 'device', 'is_not', 'mobile' ), false ],
            'unknown type, any operator' => [ self::rule( 'weather', 'not_in', [ 'rain' ] ), false ],
        ];
    }

    public function test_a_guest_has_no_role(): void {
        $guest = array_merge( self::CONTEXT, [ 'roles' => [] ] );

        $this->assertFalse( LayoutAssignment::matches( self::rule( 'customer_role', 'in', [ 'customer' ] ), $guest ) );
        $this->assertTrue( LayoutAssignment::matches( self::rule( 'customer_role', 'not_in', [ 'customer' ] ), $guest ) );
    }

    public function test_groups_or_and_conditions_and(): void {
        $de_and_wholesale = [
            [
                [
                    'type'     => 'country',
                    'operator' => 'in',
                    'value'    => [ 'DE' ],
                ],
                [
                    'type'     => 'customer_role',
                    'operator' => 'in',
                    'value'    => [ 'wholesale' ],
                ],
            ],
        ];
        $or_mobile        = array_merge( $de_and_wholesale, self::rule( 'device', 'is', 'mobile' ) );

        $this->assertFalse( LayoutAssignment::matches( $de_and_wholesale, self::CONTEXT ) );
        $this->assertTrue( LayoutAssignment::matches( $or_mobile, self::CONTEXT ) );
    }

    /** Active, published, with $rules; activated in call order, so the last call is the most recent. */
    private static function active_layout( array $rules ): int {
        $id = LayoutFactory::serve( LayoutFactory::checkout_markup() );
        if ( [] !== $rules ) {
            update_post_meta( $id, Layout::META_RULES, $rules );
        }

        return $id;
    }

    private static function served(): ?int {
        LayoutFactory::forget_served_layout();
        $layout = CheckoutPage::get_instance()->layout();

        return null === $layout ? null : $layout->id;
    }

    public function test_the_layout_whose_rules_match_is_served(): void {
        $germany = self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );

        $this->assertSame( $germany, self::served() );
    }

    public function test_no_match_leaves_woocommerce_alone(): void {
        self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        self::active_layout( self::rule( 'country', 'in', [ 'IT' ] ) );
        WC()->customer->set_billing_country( 'DE' );

        $this->assertNull( self::served() );
    }

    public function test_a_store_without_rules_serves_the_most_recently_activated(): void {
        self::active_layout( [] );
        $latest = self::active_layout( [] );

        $this->assertSame( $latest, self::served() );
    }

    /** No rules means everyone: the shopper falls through a layout whose rules miss to the next one. */
    public function test_a_layout_without_rules_serves_whoever_the_layouts_before_it_miss(): void {
        $everyone = self::active_layout( [] );
        self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );

        WC()->customer->set_billing_country( 'DE' );
        $this->assertNotSame( $everyone, self::served() );

        WC()->customer->set_billing_country( 'FR' );
        $this->assertSame( $everyone, self::served() );
    }

    public function test_when_several_match_the_most_recently_activated_wins(): void {
        self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        $latest = self::active_layout( self::rule( 'country', 'in', [ 'DE', 'AT' ] ) );
        WC()->customer->set_billing_country( 'DE' );

        $this->assertSame( $latest, self::served() );
    }

    public function test_a_layout_without_rules_ends_the_search(): void {
        self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        $catch_all = self::active_layout( [] );

        WC()->customer->set_billing_country( 'DE' );

        $this->assertSame( $catch_all, self::served() );
    }

    public function test_an_empty_active_set_leaves_woocommerce_alone(): void {
        $this->assertNull( self::served() );
    }

    public function test_ajax_keeps_the_layout_the_page_load_picked(): void {
        $germany = self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        $france  = self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );
        $this->assertSame( $germany, self::served() );

        WC()->customer->set_billing_country( 'FR' );
        add_filter( 'wp_doing_ajax', '__return_true' );
        $this->assertSame( $germany, self::served() );

        remove_all_filters( 'wp_doing_ajax' );
        $this->assertSame( $france, self::served() );
    }

    public function test_an_order_submit_keeps_the_pinned_layout(): void {
        $germany = self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );
        self::served();

        WC()->customer->set_billing_country( 'FR' );
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertSame( $germany, self::served() );
    }

    public function test_only_the_checkout_page_load_pins(): void {
        $germany = self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );
        self::served();

        $this->go_to( home_url() );
        WC()->customer->set_billing_country( 'FR' );
        self::served();

        add_filter( 'wp_doing_ajax', '__return_true' );
        $this->assertSame( $germany, self::served() );
    }

    public function test_context_reads_the_cart(): void {
        $parent = new \WC_Product_Variable();
        $parent->save();
        $variation = new \WC_Product_Variation();
        $variation->set_parent_id( $parent->get_id() );
        $variation->set_regular_price( '20' );
        $variation->save();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart( $parent->get_id(), 1, $variation->get_id() );

        $condition = static fn( string $type, string $operator, $value ): array => [
            'type'     => $type,
            'operator' => $operator,
            'value'    => $value,
        ];
        // Parent and variation both count.
        $cart_layout = self::active_layout(
            [
                [
                    $condition( 'product_in_cart', 'in', [ $parent->get_id() ] ),
                    $condition( 'product_in_cart', 'in', [ $variation->get_id() ] ),
                ],
            ]
        );

        $this->assertSame( $cart_layout, self::served() );
    }

    public function test_a_pin_to_a_layout_no_longer_active_is_ignored(): void {
        $germany = self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        $france  = self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );
        self::served();

        ( new LayoutRepository() )->deactivate( $germany );
        WC()->customer->set_billing_country( 'FR' );
        add_filter( 'wp_doing_ajax', '__return_true' );

        $this->assertSame( $france, self::served() );
    }

    public function test_a_page_load_that_served_woocommerce_pins_that_too(): void {
        self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        WC()->customer->set_billing_country( 'FR' );
        $this->assertNull( self::served() );

        WC()->customer->set_billing_country( 'DE' );
        add_filter( 'wp_doing_ajax', '__return_true' );

        $this->assertNull( self::served() );
    }

    public function test_a_checkout_endpoint_does_not_pin(): void {
        $germany = self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );
        self::served();

        $GLOBALS['wp']->query_vars['order-pay'] = '1';
        WC()->customer->set_billing_country( 'FR' );
        self::served();
        unset( $GLOBALS['wp']->query_vars['order-pay'] );

        add_filter( 'wp_doing_ajax', '__return_true' );
        $this->assertSame( $germany, self::served() );
    }

    public function test_free_serves_its_active_layout_whatever_its_rules(): void {
        self::active_layout( self::rule( 'country', 'in', [ 'DE' ] ) );
        $latest = self::active_layout( self::rule( 'country', 'in', [ 'FR' ] ) );
        WC()->customer->set_billing_country( 'DE' );

        $pro = new \ReflectionProperty( Plan::class, 'pro' );
        $pro->setAccessible( true );
        $pro->setValue( null, false );

        try {
            $this->assertSame( $latest, self::served() );
        } finally {
            $pro->setValue( null, true );
        }
    }
}
