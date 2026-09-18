<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

class LayoutModelTest extends WP_UnitTestCase {

    private LayoutRepository $repository;

    public function set_up(): void {
        parent::set_up();
        $this->repository = new LayoutRepository();
    }

    public function test_wire_status_is_the_post_status(): void {
        $ids = [
            LayoutFactory::create_draft(),
            LayoutFactory::create_draft( [ 'post_status' => 'pending' ] ),
            LayoutFactory::create_scheduled(),
            $this->repository->publish( LayoutFactory::create_draft() )->id,
        ];

        $trashed = LayoutFactory::create_draft();
        wp_trash_post( $trashed );
        $ids[] = $trashed;

        foreach ( $ids as $id ) {
            $this->assertSame( get_post( $id )->post_status, $this->repository->find( $id )->to_summary_array()['status'] );
        }
    }

    public function test_is_published_follows_post_status_not_the_snapshot(): void {
        $published = LayoutFactory::create_draft( [ 'post_status' => 'publish' ] );
        $this->assertTrue( $this->repository->find( $published )->is_published() );

        $reverted = $this->repository->publish( LayoutFactory::create_draft() )->id;
        wp_update_post(
            [
                'ID'          => $reverted,
                'post_status' => 'draft',
            ]
        );
        $this->assertNotEmpty( get_post_meta( $reverted, Layout::META_PUBLISHED, true ) );
        $this->assertFalse( $this->repository->find( $reverted )->is_published() );
    }

    public function test_scheduled_at_is_set_only_while_future(): void {
        $scheduled = $this->repository->find( LayoutFactory::create_scheduled() );
        $this->assertNotNull( $scheduled->scheduled_at );
        $this->assertNotNull( $scheduled->to_summary_array()['scheduled_at'] );

        $draft = $this->repository->find( LayoutFactory::create_draft() );
        $this->assertNull( $draft->scheduled_at );
    }

    public function test_has_unpublished_changes_when_draft_differs_from_snapshot(): void {
        $id = LayoutFactory::create_draft();
        $this->repository->publish( $id );
        $this->assertFalse( $this->repository->find( $id )->has_unpublished_changes() );

        wp_update_post(
            [
                'ID'           => $id,
                'post_content' => LayoutFactory::changed_markup(),
            ]
        );

        $this->assertTrue( $this->repository->find( $id )->has_unpublished_changes() );
    }
}
