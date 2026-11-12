<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\CheckoutExtraFields;
use YayCheckout\Renderer\CheckoutRequiredFields;
use YayCheckout\Tests\Support\LayoutFactory;

/**
 * P-2: a wallet order (a gateway's marker posted) skips the layout's own required rules; a normal checkout keeps them.
 */
class ExpressWalletOrderTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/billing --><!-- wp:yaycheckout/field {"fieldKey":"billing_company","label":"Company","required":true} /--><!-- /wp:yaycheckout/billing --><!-- wp:yaycheckout/extra-field {"fieldKey":"delivery_note","label":"Delivery note","required":true} /--><!-- /wp:yaycheckout/checkout -->' );
        $this->forget_memo( CheckoutExtraFields::get_instance() );
        $this->forget_memo( CheckoutRequiredFields::get_instance(), false );
    }

    public function tear_down(): void {
        $_POST = [];
        $this->forget_memo( CheckoutExtraFields::get_instance() );
        $this->forget_memo( CheckoutRequiredFields::get_instance(), false );
        parent::tear_down();
    }

    public function test_wallet_order_skips_layout_required_extra_field_and_address_flags(): void {
        $_POST = [ 'payment_request_type' => 'google_pay' ];

        $this->assertFalse( $this->validation_errors()->has_errors() );
        $this->assertFalse( ( new \WC_Checkout() )->get_checkout_fields()['billing']['billing_company']['required'] );

        $_POST = [];

        $this->assertContains( 'yaycheckout_extra_delivery_note', $this->validation_errors()->get_error_codes() );
        $this->assertTrue( ( new \WC_Checkout() )->get_checkout_fields()['billing']['billing_company']['required'] );
    }

    private function validation_errors(): \WP_Error {
        $errors = new \WP_Error();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.
        do_action( 'woocommerce_after_checkout_validation', [], $errors );

        return $errors;
    }

    /** @param mixed $unread The property's "not read yet" value. */
    private function forget_memo( object $instance, $unread = null ): void {
        $memo = new \ReflectionProperty( get_class( $instance ), 'fields' );
        $memo->setAccessible( true );
        $memo->setValue( $instance, $unread );
    }
}
