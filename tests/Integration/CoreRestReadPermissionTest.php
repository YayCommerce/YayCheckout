<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** Core serves any 'publish' post to anyone; layouts must stay behind manage_woocommerce on wp/v2 too. */
class CoreRestReadPermissionTest extends WP_UnitTestCase {

    private int $published;

    public function set_up(): void {
        parent::set_up();
        $this->published = ( new LayoutRepository() )->publish( LayoutFactory::create_draft() )->id;
    }

    public function test_anonymous_reads_are_refused(): void {
        wp_set_current_user( 0 );

        $this->assertSame( 401, $this->core_get( '/' . $this->published )->get_status() );
        $this->assertSame( 401, $this->core_get( '' )->get_status() );
    }

    public function test_shop_manager_reads_pass(): void {
        LayoutFactory::as_shop_manager();

        $this->assertSame( 200, $this->core_get( '/' . $this->published )->get_status() );

        $collection = $this->core_get( '' );
        $this->assertSame( 200, $collection->get_status() );
        $this->assertSame( [ $this->published ], array_column( $collection->get_data(), 'id' ) );
    }

    /** The editor's Move to trash reads the same permission callback, so a refused DELETE also hides the button. */
    public function test_embedded_editor_cannot_trash(): void {
        LayoutFactory::as_shop_manager();
        $route = '/wp/v2/' . Layout::POST_TYPE . '/' . $this->published;

        $tagged = new \WP_REST_Request( 'DELETE', $route );
        $tagged->set_header( 'X-YayCheckout-Embed', '1' );
        $this->assertSame( 403, rest_do_request( $tagged )->get_status() );
        $this->assertSame( 'publish', get_post( $this->published )->post_status );

        $this->assertSame( 200, rest_do_request( new \WP_REST_Request( 'DELETE', $route ) )->get_status() );
        $this->assertSame( 'trash', get_post( $this->published )->post_status );
    }

    private function core_get( string $suffix ): \WP_REST_Response {
        return rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/' . Layout::POST_TYPE . $suffix ) );
    }
}
