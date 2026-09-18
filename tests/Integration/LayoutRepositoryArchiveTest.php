<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** Archived means kept: no path into the trash may leave the purge timestamp behind. */
class LayoutRepositoryArchiveTest extends WP_UnitTestCase {

    private LayoutRepository $repository;

    public function set_up(): void {
        parent::set_up();
        $this->repository = new LayoutRepository();
    }

    public function test_archive_deactivates_and_skips_the_purge_timestamp(): void {
        $id = LayoutFactory::create_draft();
        $this->repository->activate( $id );

        $layout = $this->repository->archive( $id );

        $this->assertSame( 'trash', $layout->post_status );
        $this->assertFalse( $layout->is_active );
        $this->assertSame( 'publish', get_post_meta( $id, '_wp_trash_meta_status', true ) );
        $this->assertSame( [], get_post_meta( $id, '_wp_trash_meta_time', false ) );
    }

    public function test_archiving_twice_and_publishing_archived_are_refused(): void {
        $id = LayoutFactory::create_draft();
        $this->repository->archive( $id );

        try {
            $this->repository->archive( $id );
            $this->fail( 'Second archive should throw.' );
        } catch ( \InvalidArgumentException $ex ) {
            $this->assertSame( 'trash', get_post( $id )->post_status );
        }

        $this->expectException( \InvalidArgumentException::class );
        $this->repository->publish( $id );
    }

    public function test_core_trash_paths_drop_the_purge_timestamp_too(): void {
        LayoutFactory::as_shop_manager();
        $id = LayoutFactory::create_draft();

        $response = rest_do_request( new \WP_REST_Request( 'DELETE', '/wp/v2/' . Layout::POST_TYPE . '/' . $id ) );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'trash', get_post( $id )->post_status );
        $this->assertSame( [], get_post_meta( $id, '_wp_trash_meta_time', false ) );
    }
}
