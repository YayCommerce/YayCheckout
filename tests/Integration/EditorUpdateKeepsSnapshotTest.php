<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** Gutenberg "Update" on a published layout edits the draft only; the snapshot waits for an explicit publish. */
class EditorUpdateKeepsSnapshotTest extends WP_UnitTestCase {

    public function test_updating_a_published_layout_does_not_touch_the_snapshot(): void {
        LayoutFactory::as_shop_manager();
        $repository = new LayoutRepository();
        $id         = LayoutFactory::create_draft();
        $repository->publish( $id );
        $published_at = get_post_meta( $id, Layout::META_PUBLISHED_AT, true );

        $response = LayoutFactory::rest_update( $id, [ 'content' => LayoutFactory::changed_markup() ] );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'publish', get_post( $id )->post_status );
        $this->assertSame( LayoutFactory::checkout_markup(), get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertSame( $published_at, get_post_meta( $id, Layout::META_PUBLISHED_AT, true ) );
        $this->assertTrue( $repository->find( $id )->has_unpublished_changes() );
    }
}
