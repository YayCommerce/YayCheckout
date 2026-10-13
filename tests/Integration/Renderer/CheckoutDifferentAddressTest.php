<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Tests\Support\LayoutFactory;

/** Shipping mode copies the shipping address into billing on submit; billing mode copies nothing. */
class CheckoutDifferentAddressTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        add_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/billing /--><!-- wp:yaycheckout/different-address /--><!-- wp:yaycheckout/shipping /--><!-- wp:yaycheckout/payment /--><!-- wp:yaycheckout/place-order /--><!-- /wp:yaycheckout/checkout -->' );

        $_POST = [
            'woocommerce-process-checkout-nonce' => 'x',
            'ship_to_different_address'          => '1',
            'billing_email'                      => 'shopper@example.com',
            'shipping_first_name'                => 'Ada',
            'shipping_last_name'                 => 'Lovelace',
            'shipping_address_1'                 => '1 Main St',
            'shipping_city'                      => 'Toronto',
            'shipping_state'                     => 'ON',
            'shipping_postcode'                  => 'M5V 2T6',
            'shipping_country'                   => 'CA',
        ];
    }

    public function tear_down(): void {
        $_POST = [];
        parent::tear_down();
    }

    public function test_shipping_mode_copies_shipping_into_billing(): void {
        update_option( 'woocommerce_ship_to_destination', 'shipping' );
        $_POST['yaycheckout_same_billing'] = '1';

        $checkout = new \WC_Checkout();
        $data     = $checkout->get_posted_data();

        foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'state', 'postcode', 'country' ] as $key ) {
            $this->assertSame( $data[ 'shipping_' . $key ], $data[ 'billing_' . $key ], $key );
        }

        $this->assertSame( 'shopper@example.com', $data['billing_email'] );
        $this->assertFalse( $checkout->get_checkout_fields( 'billing' )['billing_address_1']['required'] );
    }

    public function test_billing_mode_copies_nothing(): void {
        update_option( 'woocommerce_ship_to_destination', 'billing' );
        $_POST['yaycheckout_same_billing'] = '1';

        $data = ( new \WC_Checkout() )->get_posted_data();

        $this->assertSame( '', $data['billing_address_1'] );
        $this->assertSame( '1 Main St', $data['shipping_address_1'] );
    }
}
