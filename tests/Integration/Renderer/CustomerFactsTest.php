<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Enums\ConditionType;
use YayCheckout\Renderer\CustomerFacts;

/** P-1: the country conditions test the customer's stored countries, with the address the form opens with as fallback. */
class CustomerFactsTest extends WP_UnitTestCase {

    private const LOADED = 'woocommerce_cart_loaded_from_session';

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        update_option( 'woocommerce_default_country', 'US:CA' );
        update_option( 'woocommerce_default_customer_address', 'base' );
    }

    /** @param bool $loaded Whether WooCommerce has loaded the session, as did_action() reports it. */
    private static function session_loaded( bool $loaded ): void {
        global $wp_actions;

        if ( $loaded ) {
            $wp_actions[ self::LOADED ] = max( 1, $wp_actions[ self::LOADED ] ?? 0 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the request stage.
        } else {
            unset( $wp_actions[ self::LOADED ] );
        }
    }

    private static function customer( string $billing, string $shipping ): void {
        WC()->customer->set_billing_country( $billing );
        WC()->customer->set_shipping_country( $shipping );
    }

    public function test_context_reads_customer_countries(): void {
        self::session_loaded( true );

        self::customer( 'DE', 'FR' );
        $this->assertSame(
            [
                ConditionType::BILLING_COUNTRY  => 'DE',
                ConditionType::SHIPPING_COUNTRY => 'FR',
            ],
            CustomerFacts::context()
        );

        self::customer( 'DE', '' );
        $this->assertSame( 'DE', CustomerFacts::context()[ ConditionType::SHIPPING_COUNTRY ], 'An empty shipping country is the billing one.' );

        self::customer( '', '' );
        $this->assertSame(
            [
                ConditionType::BILLING_COUNTRY  => 'US',
                ConditionType::SHIPPING_COUNTRY => 'US',
            ],
            CustomerFacts::context(),
            'An empty customer is in the store base country.'
        );

        self::session_loaded( false );
        self::customer( 'DE', 'FR' );
        $this->assertSame( [], CustomerFacts::context(), 'Nothing before the session loads.' );
    }
}
