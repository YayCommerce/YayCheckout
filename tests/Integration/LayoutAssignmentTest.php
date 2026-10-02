<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Services\LayoutAssignment;
use YayCheckout\Support\Plan;
use YayCheckout\Tests\Support\LayoutFactory;

/** Pins which layout the checkout serves once rules are set. */
class LayoutAssignmentTest extends WP_UnitTestCase {

    private const CONTEXT = [
        'customer_role' => [ 'customer' ],
        'login_status'  => 'logged_in',
        'device'        => 'mobile',
    ];

    public function tear_down(): void {
        remove_all_filters( 'wp_doing_ajax' );
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
            'role in'                    => [ self::rule( 'customer_role', 'in', [ 'customer' ] ), true ],
            'role not in'                => [ self::rule( 'customer_role', 'not_in', [ 'wholesale' ] ), true ],
            'login status is'            => [ self::rule( 'login_status', 'is', 'logged_in' ), true ],
            'login status is not'        => [ self::rule( 'login_status', 'is_not', 'logged_in' ), false ],
            'device is'                  => [ self::rule( 'device', 'is', 'mobile' ), true ],
            'device is not'              => [ self::rule( 'device', 'is_not', 'mobile' ), false ],
            'unknown type, any operator' => [ self::rule( 'country', 'not_in', [ 'DE' ] ), false ],
        ];
    }

    public function test_a_guest_has_no_role(): void {
        $guest = array_merge(
            self::CONTEXT,
            [
                'customer_role' => [],
                'login_status'  => 'guest',
            ]
        );

        $this->assertFalse( LayoutAssignment::matches( self::rule( 'customer_role', 'in', [ 'customer' ] ), $guest ) );
        $this->assertTrue( LayoutAssignment::matches( self::rule( 'customer_role', 'not_in', [ 'customer' ] ), $guest ) );
    }

    public function test_groups_or_and_conditions_and(): void {
        $wholesale_on_mobile = [
            [
                [
                    'type'     => 'customer_role',
                    'operator' => 'in',
                    'value'    => [ 'wholesale' ],
                ],
                [
                    'type'     => 'device',
                    'operator' => 'is',
                    'value'    => 'mobile',
                ],
            ],
        ];
        $or_logged_in        = array_merge( $wholesale_on_mobile, self::rule( 'login_status', 'is', 'logged_in' ) );

        $this->assertFalse( LayoutAssignment::matches( $wholesale_on_mobile, self::CONTEXT ) );
        $this->assertTrue( LayoutAssignment::matches( $or_logged_in, self::CONTEXT ) );
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

    private function log_in( string $role = 'customer' ): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
    }

    public function test_the_layout_whose_rules_match_is_served(): void {
        $guests = self::active_layout( self::rule( 'login_status', 'is', 'guest' ) );
        self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );

        $this->assertSame( $guests, self::served() );
    }

    public function test_no_match_leaves_woocommerce_alone(): void {
        self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );
        self::active_layout( self::rule( 'customer_role', 'in', [ 'customer' ] ) );

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
        self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );

        $this->assertSame( $everyone, self::served() );

        $this->log_in();
        $this->assertNotSame( $everyone, self::served() );
    }

    public function test_when_several_match_the_most_recently_activated_wins(): void {
        self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );
        $latest = self::active_layout( self::rule( 'customer_role', 'in', [ 'customer', 'wholesale' ] ) );
        $this->log_in();

        $this->assertSame( $latest, self::served() );
    }

    public function test_a_layout_without_rules_ends_the_search(): void {
        self::active_layout( self::rule( 'login_status', 'is', 'guest' ) );
        $catch_all = self::active_layout( [] );

        $this->assertSame( $catch_all, self::served() );
    }

    public function test_an_empty_active_set_leaves_woocommerce_alone(): void {
        $this->assertNull( self::served() );
    }

    /** A user of another network site is logged in with no role here. */
    public function test_a_logged_in_user_without_a_role_is_not_a_guest(): void {
        $logged_in = self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );
        $this->log_in( '' );

        $this->assertSame( [], wp_get_current_user()->roles );
        $this->assertSame( $logged_in, self::served() );
    }

    /** No pin: every request picks from the facts it sees, and the conditions read none a shopper changes mid-form. */
    public function test_an_ajax_request_picks_from_its_own_facts(): void {
        $guests  = self::active_layout( self::rule( 'login_status', 'is', 'guest' ) );
        $members = self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );
        $this->assertSame( $guests, self::served() );

        $this->log_in();
        add_filter( 'wp_doing_ajax', '__return_true' );

        $this->assertSame( $members, self::served() );
    }

    public function test_free_serves_its_active_layout_whatever_its_rules(): void {
        self::active_layout( self::rule( 'login_status', 'is', 'guest' ) );
        $latest = self::active_layout( self::rule( 'login_status', 'is', 'logged_in' ) );

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
