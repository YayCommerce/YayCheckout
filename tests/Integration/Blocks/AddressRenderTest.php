<?php
namespace YayCheckout\Tests\Integration\Blocks;

use WP_UnitTestCase;

/** Address blocks render WooCommerce's own fields and shipping contract. */
class AddressRenderTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        wc_load_cart();
        WC()->cart->empty_cart();
    }

    public function test_postcode_field_carries_woocommerces_classes_and_value(): void {
        add_filter( 'woocommerce_checkout_get_value', static fn( $v, $k ) => 'billing_postcode' === $k ? '10115' : $v, 10, 2 );
        $html = do_blocks( '<!-- wp:yaycheckout/field {"fieldKey":"billing_postcode","label":"Zip","required":true} /-->' );

        $this->assertMatchesRegularExpression( '#<p class="[^"]*\baddress-field\b[^"]*" id="billing_postcode_field"#', $html );
        $this->assertStringContainsString( 'value="10115"', $html );
        $this->assertStringContainsString( 'Zip', $html );
    }

    public function test_country_field_refreshes_totals(): void {
        $html = do_blocks( '<!-- wp:yaycheckout/field {"fieldKey":"billing_country","type":"country","label":"Country"} /-->' );

        $this->assertMatchesRegularExpression( '#<p class="[^"]*\bupdate_totals_on_change\b[^"]*" id="billing_country_field"#', $html );
    }

    public function test_email_block_passes_the_checkout_value(): void {
        add_filter( 'woocommerce_checkout_get_value', static fn( $v, $k ) => 'billing_email' === $k ? 'a@example.com' : $v, 10, 2 );
        $html = do_blocks( '<!-- wp:yaycheckout/email /-->' );

        $this->assertStringContainsString( 'value="a@example.com"', $html );
    }

    public function test_shipping_prints_woocommerces_ship_to_different_address_contract(): void {
        $this->add_product_to_cart( false );
        $html = do_blocks( '<!-- wp:yaycheckout/shipping --><!-- wp:yaycheckout/field {"fieldKey":"shipping_city","label":"City"} /--><!-- /wp:yaycheckout/shipping -->' );

        $this->assertMatchesRegularExpression( '#class="[^"]*\bwoocommerce-shipping-fields\b#', $html );
        $this->assertMatchesRegularExpression( '#id="ship-to-different-address".*name="ship_to_different_address"#s', $html );
        $this->assertMatchesRegularExpression( '#<div class="shipping_address">.*id="shipping_city_field"#s', $html );
    }

    public function test_shipping_prints_nothing_for_a_virtual_cart(): void {
        $this->add_product_to_cart( true );

        $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/shipping /-->' ) ) );
    }

    public function test_shipping_prints_nothing_when_shipping_goes_to_billing_only(): void {
        update_option( 'woocommerce_ship_to_destination', 'billing_only' );
        $this->add_product_to_cart( false );

        $this->assertSame( '', trim( do_blocks( '<!-- wp:yaycheckout/shipping /-->' ) ) );
    }

    /** Adds a product to the cart; needs_shipping_address() needs shipping enabled and at least one method. */
    private function add_product_to_cart( bool $virtual ): void {
        update_option( 'woocommerce_ship_to_countries', '' );
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name( 'Everywhere' );
        $zone->save();
        $zone->add_shipping_method( 'flat_rate' );

        $product = new \WC_Product_Simple();
        $product->set_regular_price( '10' );
        $product->set_virtual( $virtual );
        $product->save();
        WC()->cart->add_to_cart( $product->get_id() );
    }
}
