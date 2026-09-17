<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

class PluginBootTest extends WP_UnitTestCase {

    public function test_layout_post_type_is_registered(): void {
        $this->assertTrue( post_type_exists( Layout::POST_TYPE ) );
    }

    public function test_layout_rest_insert_is_guarded(): void {
        $this->assertNotFalse( has_filter( 'rest_pre_insert_' . Layout::POST_TYPE ) );
    }

    public function test_layouts_route_requires_manage_woocommerce(): void {
        wp_set_current_user( 0 );
        $this->assertSame( 401, rest_do_request( new \WP_REST_Request( 'GET', '/yaycheckout/v1/layouts' ) )->get_status() );

        LayoutFactory::as_shop_manager();
        $this->assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/yaycheckout/v1/layouts' ) )->get_status() );
    }
}
