<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

class LayoutRepositoryPublishTest extends WP_UnitTestCase {

    private LayoutRepository $repository;

    public function set_up(): void {
        parent::set_up();
        $this->repository = new LayoutRepository();
    }

    public function test_publish_copies_draft_to_snapshot_and_flips_status(): void {
        $id = LayoutFactory::create_draft();

        $this->repository->publish( $id );

        $this->assertSame( get_post( $id )->post_content, get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertSame(
            [ 'fields', 'extra_fields', 'slots', 'order_modes' ],
            array_keys( get_post_meta( $id, Layout::META_DERIVED, true ) )
        );
        $this->assertNotEmpty( get_post_meta( $id, Layout::META_PUBLISHED_AT, true ) );
        $this->assertSame( 'publish', get_post( $id )->post_status );
    }

    public function test_publish_with_empty_markup_throws_and_writes_nothing(): void {
        $id = LayoutFactory::create_draft( [ 'post_content' => '' ] );

        $this->expectException( \InvalidArgumentException::class );

        try {
            $this->repository->publish( $id );
        } finally {
            $this->assertSame( [], get_post_meta( $id, Layout::META_PUBLISHED, false ) );
            $this->assertSame( [], get_post_meta( $id, Layout::META_DERIVED, false ) );
            $this->assertSame( [], get_post_meta( $id, Layout::META_PUBLISHED_AT, false ) );
            $this->assertSame( 'draft', get_post( $id )->post_status );
        }
    }

    public function test_publish_brings_a_scheduled_layout_forward_and_clears_the_cron_event(): void {
        $id = LayoutFactory::create_scheduled();
        $this->assertNotFalse( wp_next_scheduled( 'publish_future_post', [ $id ] ) );

        $this->repository->publish( $id );

        $post = get_post( $id );
        $this->assertSame( 'publish', $post->post_status );
        $this->assertLessThanOrEqual( time(), strtotime( $post->post_date_gmt . ' GMT' ) );
        $this->assertFalse( wp_next_scheduled( 'publish_future_post', [ $id ] ) );
        $this->assertNull( $this->repository->find( $id )->scheduled_at );
    }

    public function test_republishing_unchanged_content_keeps_the_revision_pointer(): void {
        $id = LayoutFactory::create_draft();

        $this->repository->publish( $id );
        $revision = get_post_meta( $id, Layout::META_PUBLISHED_REVISION, true );
        $this->assertNotEmpty( $revision );

        $this->repository->publish( $id );

        $this->assertSame( $revision, get_post_meta( $id, Layout::META_PUBLISHED_REVISION, true ) );
    }
}
