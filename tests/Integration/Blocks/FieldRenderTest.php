<?php
namespace YayCheckout\Tests\Integration\Blocks;

use WP_UnitTestCase;

/** Field and Email blocks print the shopper's session values and WooCommerce's behaviour classes, so a reload keeps the address. */
class FieldRenderTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        WC()->customer->set_props(
            [
                'billing_country'   => 'US',
                'billing_state'     => 'TX',
                'billing_city'      => 'Austin',
                'billing_address_1' => '1 Main St',
                'billing_email'     => 'shopper@example.test',
            ]
        );
    }

    private function field( string $key, string $type = 'text' ): string {
        return do_blocks( '<!-- wp:yaycheckout/field {"fieldKey":"' . $key . '","type":"' . $type . '","label":"' . $key . '"} /-->' );
    }

    public function test_fields_print_the_session_values(): void {
        $this->assertStringContainsString( 'value="Austin"', $this->field( 'billing_city' ) );
        $this->assertStringContainsString( 'value="1 Main St"', $this->field( 'billing_address_1' ) );
        $this->assertMatchesRegularExpression( '#<option value="US"\s+selected#', $this->field( 'billing_country', 'country' ) );
        $this->assertStringContainsString( 'value="shopper@example.test"', do_blocks( '<!-- wp:yaycheckout/email /-->' ) );
    }

    public function test_address_rows_carry_the_classes_checkout_js_listens_on(): void {
        $this->assertMatchesRegularExpression( '#<p class="form-row[^"]*\baddress-field\b#', $this->field( 'billing_city' ) );

        $country = $this->field( 'billing_country', 'country' );
        $this->assertMatchesRegularExpression( '#<p class="form-row[^"]*\baddress-field\b#', $country );
        $this->assertMatchesRegularExpression( '#<p class="form-row[^"]*\bupdate_totals_on_change\b#', $country );

        $name = $this->field( 'billing_first_name' );
        $this->assertStringNotContainsString( 'address-field', $name );
        $this->assertStringNotContainsString( 'form-row-first', $name );
    }

    public function test_apartment_prints_open_only_when_it_holds_a_value(): void {
        $this->assertStringContainsString( 'yaycheckout-disclosure__panel', $this->field( 'billing_address_2' ) );

        WC()->customer->set_billing_address_2( 'Unit 4' );
        $filled = $this->field( 'billing_address_2' );

        $this->assertStringNotContainsString( 'yaycheckout-disclosure__panel', $filled );
        $this->assertStringContainsString( 'value="Unit 4"', $filled );
    }
}
