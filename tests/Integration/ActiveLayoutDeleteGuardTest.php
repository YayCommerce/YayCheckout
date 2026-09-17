<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** The active layout is what shoppers see; trashing or deleting it must be refused until it is deactivated. */
class ActiveLayoutDeleteGuardTest extends WP_UnitTestCase {

    private LayoutRepository $repository;

    private int $id;

    public function set_up(): void {
        parent::set_up();
        LayoutFactory::as_shop_manager();
        $this->repository = new LayoutRepository();
        $this->id         = LayoutFactory::create_draft();
        $this->repository->activate( $this->id );
    }

    public function test_core_rest_delete_is_refused_while_active(): void {
        $this->assertSame( 403, $this->rest_delete()->get_status() );
        $this->assertSame( 'publish', get_post( $this->id )->post_status );
        $this->assertFalse( current_user_can( 'delete_post', $this->id ) );
    }

    public function test_direct_trash_and_delete_are_refused_while_active(): void {
        $this->assertFalse( wp_trash_post( $this->id ) );
        $this->assertFalse( wp_delete_post( $this->id, true ) );
        $this->assertSame( 'publish', get_post( $this->id )->post_status );
    }

    public function test_deactivated_layout_can_be_trashed(): void {
        $this->repository->deactivate( $this->id );

        $this->assertTrue( current_user_can( 'delete_post', $this->id ) );
        $this->assertSame( 200, $this->rest_delete()->get_status() );
        $this->assertSame( 'trash', get_post( $this->id )->post_status );
    }

    public function test_other_layouts_are_unaffected(): void {
        $other = LayoutFactory::create_draft();

        $this->assertTrue( current_user_can( 'delete_post', $other ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->id ) );
    }

    private function rest_delete(): \WP_REST_Response {
        return rest_do_request( new \WP_REST_Request( 'DELETE', '/wp/v2/' . Layout::POST_TYPE . '/' . $this->id ) );
    }
}
