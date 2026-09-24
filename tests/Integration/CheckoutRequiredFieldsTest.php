<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Renderer\CheckoutRequiredFields;
use YayCheckout\Tests\Support\LayoutFactory;

/** The served layout's required settings reaching WooCommerce's checkout fields. */
class CheckoutRequiredFieldsTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        add_filter( 'woocommerce_is_checkout', '__return_true' );
    }

    public function tear_down(): void {
        remove_filter( 'woocommerce_is_checkout', '__return_true' );
        $this->forget_fields();
        LayoutFactory::forget_served_layout();
        parent::tear_down();
    }

    /** An all-digit Key is a valid extra field Key and becomes an integer array key; its suffix must still be found. */
    public function test_all_digit_extra_field_key_keeps_its_suffix(): void {
        LayoutFactory::serve(
            '<!-- wp:yaycheckout/checkout -->'
            . '<!-- wp:yaycheckout/contact /-->'
            . '<!-- wp:yaycheckout/extra-field {"fieldKey":"123","label":"Code","optionalText":"if any"} /-->'
            . '<!-- /wp:yaycheckout/checkout -->'
        );
        $this->forget_fields();

        $html = CheckoutRequiredFields::get_instance()->filter_form_field(
            '<label>Code&nbsp;<span class="optional">(optional)</span></label>',
            '123',
            [ 'required' => false ]
        );

        $this->assertStringContainsString( '<span class="optional">(if any)</span>', $html );
    }

    /** The renderer memoises the served layout's fields; the singleton outlives a test. */
    private function forget_fields(): void {
        $memo = new \ReflectionProperty( CheckoutRequiredFields::class, 'fields' );
        $memo->setAccessible( true );
        $memo->setValue( CheckoutRequiredFields::get_instance(), false );
    }
}
