<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Tests\Support\LayoutFactory;

/** A field the country locale hides is never required, whatever the layout says; a visible one keeps the layout's flag. */
class CheckoutRequiredFieldsHiddenTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        update_option( 'woocommerce_allowed_countries', 'all' );
        update_option( 'woocommerce_ship_to_countries', '' );
        LayoutFactory::serve( '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/billing --><!-- wp:yaycheckout/field {"fieldKey":"billing_state","label":"State","required":true} /--><!-- /wp:yaycheckout/billing --><!-- wp:yaycheckout/shipping --><!-- wp:yaycheckout/field {"fieldKey":"shipping_state","label":"State","required":true} /--><!-- /wp:yaycheckout/shipping --><!-- /wp:yaycheckout/checkout -->' );
    }

    public function tear_down(): void {
        $_POST = [];
        parent::tear_down();
    }

    public function test_locale_hidden_state_is_not_required(): void {
        $_POST  = [
            'billing_country'  => 'VN',
            'shipping_country' => 'VN',
        ];
        $fields = ( new \WC_Checkout() )->get_checkout_fields();

        $this->assertFalse( $fields['billing']['billing_state']['required'] );
        $this->assertFalse( $fields['shipping']['shipping_state']['required'] );
    }

    public function test_visible_state_keeps_layout_required(): void {
        $_POST  = [
            'billing_country'  => 'US',
            'shipping_country' => 'VN',
        ];
        $fields = ( new \WC_Checkout() )->get_checkout_fields();

        $this->assertTrue( $fields['billing']['billing_state']['required'] );
        $this->assertFalse( $fields['shipping']['shipping_state']['required'] );
    }
}
