<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

class LayoutRepositoryActivateTest extends WP_UnitTestCase {

    private LayoutRepository $repository;

    public function set_up(): void {
        parent::set_up();
        $this->repository = new LayoutRepository();
    }

    public function test_activating_a_draft_publishes_it_first(): void {
        $id = LayoutFactory::create_draft();

        $layout = $this->repository->activate( $id );

        $this->assertSame( LayoutFactory::checkout_markup(), get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertTrue( $layout->is_active );
        $this->assertTrue( $this->repository->is_active( $id ) );
    }

    public function test_activating_a_published_row_without_a_snapshot_backfills_it(): void {
        $id = LayoutFactory::create_draft();
        LayoutFactory::mark_published_without_snapshot( $id );

        $this->repository->activate( $id );

        $this->assertSame( LayoutFactory::checkout_markup(), get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertTrue( $this->repository->is_active( $id ) );
    }

    public function test_activating_a_trashed_layout_is_refused(): void {
        $id = LayoutFactory::create_draft();
        wp_trash_post( $id );

        $this->expectException( \InvalidArgumentException::class );

        try {
            $this->repository->activate( $id );
        } finally {
            $this->assertSame( 'trash', get_post( $id )->post_status );
            $this->assertFalse( $this->repository->is_active( $id ) );
        }
    }

    public function test_activating_a_published_layout_with_a_newer_draft_keeps_the_snapshot(): void {
        $id = LayoutFactory::create_draft();
        $this->repository->publish( $id );
        $published_at = get_post_meta( $id, Layout::META_PUBLISHED_AT, true );

        wp_update_post(
            [
                'ID'           => $id,
                'post_content' => LayoutFactory::changed_markup(),
            ]
        );

        $layout = $this->repository->activate( $id );

        $this->assertSame( LayoutFactory::checkout_markup(), get_post_meta( $id, Layout::META_PUBLISHED, true ) );
        $this->assertSame( $published_at, get_post_meta( $id, Layout::META_PUBLISHED_AT, true ) );
        $this->assertTrue( $layout->has_unpublished_changes() );
        $this->assertTrue( $layout->is_active );
    }
}
