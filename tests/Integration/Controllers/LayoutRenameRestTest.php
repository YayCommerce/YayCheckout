<?php
namespace YayCheckout\Tests\Integration\Controllers;

use WP_UnitTestCase;
use YayCheckout\Tests\Support\LayoutFactory;

/** PUT /layouts/{id} renames a layout, refuses a blank name and answers 404 for a missing layout. */
class LayoutRenameRestTest extends WP_UnitTestCase {

    private function put( int $id, array $body ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'PUT', "/yaycheckout/v1/layouts/{$id}" );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $body ) );

        return rest_do_request( $request );
    }

    public function test_renames_layout(): void {
        LayoutFactory::as_shop_manager();
        $id = LayoutFactory::create_draft();

        $response = $this->put( $id, [ 'name' => '  Spring <b>sale</b> ' ] );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'Spring sale', $response->get_data()['title'] );
        $this->assertSame( 'Spring sale', get_post( $id )->post_title );
        $this->assertSame( 'draft', get_post_status( $id ) );
    }

    public function test_refuses_blank_name(): void {
        LayoutFactory::as_shop_manager();
        $id = LayoutFactory::create_draft();

        $response = $this->put( $id, [ 'name' => '   ' ] );

        $this->assertSame( 400, $response->get_status() );
        $this->assertNotSame( '   ', get_post( $id )->post_title );
    }

    public function test_unknown_layout_is_404(): void {
        LayoutFactory::as_shop_manager();

        $this->assertSame( 404, $this->put( 999999, [ 'name' => 'X' ] )->get_status() );
    }
}
