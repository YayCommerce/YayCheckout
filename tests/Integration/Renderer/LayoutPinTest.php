<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Enums\ConditionType;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Renderer\LayoutPin;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Support\Plan;
use YayCheckout\Tests\Support\LayoutFactory;

/**
 * A checkout page load pins the layout its cart picked, and the form's later calls keep it while the
 * cart changes: re-picking would validate the order against a layout the shopper never saw.
 */
class LayoutPinTest extends WP_UnitTestCase {

    private LayoutRepository $layouts;

    private int $digital;

    private int $shirt;

    /** The cookie value LayoutPin wrote, caught before it is sent. */
    private ?string $cookie = null;

    public function set_up(): void {
        parent::set_up();

        if ( ! Plan::is_pro() ) {
            $this->markTestSkipped( 'Pins only where rules apply: the Pro plan.' );
        }

        wc_load_cart();
        WC()->cart->empty_cart();
        WC()->session->set( LayoutPin::SESSION_KEY, null );

        add_filter(
            'woocommerce_set_cookie_enabled',
            function ( $enabled, $name, $value ) {
                if ( LayoutPin::COOKIE === $name ) {
                    $this->cookie = $value;
                }

                return false;
            },
            10,
            3
        );

        $this->layouts = new LayoutRepository();
        $this->digital = $this->product();
        $this->shirt   = $this->product();
        // CartFacts reads the cart only once WooCommerce says it loaded it from the session.
        do_action( 'woocommerce_cart_loaded_from_session', WC()->cart ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook, fired as it would be.
    }

    public function tear_down(): void {
        unset( $_COOKIE[ LayoutPin::COOKIE ] );
        parent::tear_down();
    }

    private function product(): int {
        $product = new \WC_Product_Simple();
        $product->set_regular_price( '10' );

        return $product->save();
    }

    /** Active, with "Products contains any of [$product]"; activated last, so tried first. */
    private function serve_for( int $product ): int {
        $id = LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $this->layouts->set_rules(
            $id,
            [
                [
                    [
                        'type'     => ConditionType::PRODUCT,
                        'operator' => ConditionType::CONTAINS_ANY,
                        'value'    => [ $product ],
                    ],
                ],
            ]
        );

        return $id;
    }

    private function cart_holds( int $product ): void {
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart( $product, 1 );
    }

    public function test_later_calls_keep_the_page_loads_pick_while_the_cart_changes(): void {
        $everyone = LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $digital  = $this->serve_for( $this->digital );

        $this->cart_holds( $this->digital );
        $this->assertSame( $digital, LayoutPin::pick( $this->layouts, CheckoutPage::shopper_context() )->id );

        // The stepper swaps the cart's item: the form keeps its layout until the next page load.
        $this->cart_holds( $this->shirt );
        $this->assertSame( $digital, LayoutPin::pinned( $this->layouts, CheckoutPage::shopper_context() )->id );
        $this->assertSame( $everyone, LayoutPin::pick( $this->layouts, CheckoutPage::shopper_context() )->id );
        $this->assertSame( $everyone, LayoutPin::pinned( $this->layouts, CheckoutPage::shopper_context() )->id );
    }

    public function test_a_page_load_that_picked_none_stays_none(): void {
        $this->serve_for( $this->digital );

        $this->cart_holds( $this->shirt );
        $this->assertNull( LayoutPin::pick( $this->layouts, CheckoutPage::shopper_context() ) );

        $this->cart_holds( $this->digital );
        $this->assertNull( LayoutPin::pinned( $this->layouts, CheckoutPage::shopper_context() ) );
    }

    public function test_a_pinned_layout_that_left_the_active_set_is_re_picked(): void {
        $everyone = LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $digital  = $this->serve_for( $this->digital );

        $this->cart_holds( $this->digital );
        LayoutPin::pick( $this->layouts, CheckoutPage::shopper_context() );
        $this->layouts->deactivate( $digital );

        $this->assertSame( $everyone, LayoutPin::pinned( $this->layouts, CheckoutPage::shopper_context() )->id );
    }

    /** The cookie hints the pick to the next request's setup_theme, and a tampered one hints nothing. */
    public function test_the_hint_cookie_names_the_pick_and_is_signed(): void {
        $digital = $this->serve_for( $this->digital );
        $this->cart_holds( $this->digital );
        LayoutPin::pick( $this->layouts, CheckoutPage::shopper_context() );

        $_COOKIE[ LayoutPin::COOKIE ] = $this->cookie;
        $this->assertSame( $digital, LayoutPin::hint() );

        $_COOKIE[ LayoutPin::COOKIE ] = ( $digital + 1 ) . substr( (string) $this->cookie, strlen( (string) $digital ) );
        $this->assertNull( LayoutPin::hint() );
    }

    public function test_only_cart_rules_make_the_set_depend_on_the_cart(): void {
        LayoutFactory::serve( LayoutFactory::checkout_markup() );
        $this->assertFalse( $this->layouts->depends_on_cart() );

        $this->serve_for( $this->digital );
        $this->assertTrue( $this->layouts->depends_on_cart() );
    }
}
