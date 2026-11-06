<?php
namespace YayCheckout\Tests\Integration\Pro;

use WP_UnitTestCase;
use YayCheckout\Pro\Renderer\ThankYouPage;
use YayCheckout\Support\Plan;

/**
 * The thank-you page renders an order only for whom WooCommerce's own order-received page would: the right key,
 * and then the account's owner, or a guest within the grace period or after confirming their email. A leaked
 * link must not show anyone else the order's addresses, phone and email.
 */
class ThankYouPageTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();

        if ( ! Plan::is_pro() ) {
            $this->markTestSkipped( 'The thank-you page is Pro.' );
        }
    }

    public function tear_down(): void {
        unset( $_GET['key'] );
        $_POST = [];
        parent::tear_down();
    }

    public function test_order_received_gate(): void {
        $owner   = self::factory()->user->create( [ 'role' => 'customer' ] );
        $other   = self::factory()->user->create( [ 'role' => 'customer' ] );
        $account = $this->order( $owner, time() );
        $guest   = $this->order( 0, time() - DAY_IN_SECONDS );
        $recent  = $this->order( 0, time() );

        wp_set_current_user( $owner );
        $this->assertNull( $this->validated( $account, 'wc_order_wrong' ), 'A wrong key shows nothing.' );
        $this->assertSame( $account->get_id(), $this->validated( $account )->get_id(), 'The owner sees their order.' );

        wp_set_current_user( 0 );
        $this->assertNull( $this->validated( $account ), 'An account order needs its owner logged in.' );

        wp_set_current_user( $other );
        $this->assertNull( $this->validated( $account ), 'Another account does not see it.' );

        wp_set_current_user( 0 );
        $this->assertNull( $this->validated( $guest ), 'A guest order past the grace period needs the email confirmed.' );
        $this->assertSame( $recent->get_id(), $this->validated( $recent )->get_id(), 'A guest order just placed shows, as WooCommerce shows it.' );
    }

    private function order( int $customer_id, int $created ): \WC_Order {
        $order = wc_create_order( [ 'customer_id' => $customer_id ] );
        $order->set_billing_email( 'shopper@example.com' );
        $order->set_billing_phone( '555-0100' );
        $order->set_date_created( $created );
        $order->save();

        return $order;
    }

    /** ThankYouPage::validated_order() for $order's received URL, with $key in place of its own when given. */
    private function validated( \WC_Order $order, ?string $key = null ): ?\WC_Order {
        $_GET['key'] = $key ?? $order->get_order_key();
        set_query_var( 'order-received', $order->get_id() );

        $method = new \ReflectionMethod( ThankYouPage::class, 'validated_order' );
        $method->setAccessible( true );

        return $method->invoke( ThankYouPage::get_instance() );
    }
}
