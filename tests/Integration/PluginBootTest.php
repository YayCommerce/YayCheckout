<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;

/** The harness boots WooCommerce before the plugin, so tests can reach gateways and WC pages for real. */
class PluginBootTest extends WP_UnitTestCase {

    public function test_woocommerce_and_plugin_are_booted(): void {
        $this->assertTrue( function_exists( 'WC' ) );
        $this->assertStringStartsWith( '11.', WC()->version );
        $this->assertTrue( post_type_exists( Layout::POST_TYPE ) );
        $this->assertGreaterThan( 0, wc_get_page_id( 'checkout' ) );
        $this->assertInstanceOf( \WC_Payment_Gateways::class, WC()->payment_gateways() );
    }
}
