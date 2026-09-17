<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** The embedded editor's Publish button: core wp/v2 must leave a snapshot behind. */
class EditorPublishViaRestTest extends WP_UnitTestCase {

    public function test_publishing_through_core_rest_writes_the_snapshot_once(): void {
        LayoutFactory::as_shop_manager();
        $id            = LayoutFactory::create_draft();
        $marker_writes = LayoutFactory::count_publish_marker_writes();

        $response = LayoutFactory::rest_update( $id, [ 'status' => 'publish' ] );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'publish', get_post( $id )->post_status );
        $this->assertSame( LayoutFactory::checkout_markup(), get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertSame( 1, $marker_writes() );
        $this->assertCount( 1, wp_get_post_revisions( $id ) );
    }
}
