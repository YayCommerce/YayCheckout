<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** Every status the embedded editor can set through wp/v2 shows up as-is in the Layouts list route. */
class EditorStatusSyncTest extends WP_UnitTestCase {

    private int $id;

    public function set_up(): void {
        parent::set_up();
        LayoutFactory::as_shop_manager();
        $this->id = LayoutFactory::create_draft();
    }

    public function test_publish_then_switch_to_draft(): void {
        LayoutFactory::rest_update( $this->id, [ 'status' => 'publish' ] );
        $this->assertSame( 'publish', $this->listed_row()['status'] );
        $this->assertNotNull( $this->listed_row()['published_at'] );

        LayoutFactory::rest_update( $this->id, [ 'status' => 'draft' ] );
        $row = $this->listed_row();
        $this->assertSame( 'draft', $row['status'] );
        $this->assertFalse( $row['has_unpublished_changes'] );
    }

    public function test_schedule_then_publish_now(): void {
        $date = gmdate( 'Y-m-d\TH:i:s', time() + DAY_IN_SECONDS );
        LayoutFactory::rest_update(
            $this->id,
            [
                'status'   => 'future',
                'date_gmt' => $date,
            ]
        );
        $row = $this->listed_row();
        $this->assertSame( 'future', $row['status'] );
        $this->assertSame( $date . '+00:00', $row['scheduled_at'] );
        $this->assertSame( [], get_post_meta( $this->id, Layout::META_PUBLISHED, false ) );

        LayoutFactory::rest_update(
            $this->id,
            [
				'status'   => 'publish',
				'date_gmt' => gmdate( 'Y-m-d\TH:i:s' ),
            ]
        );
        $row = $this->listed_row();
        $this->assertSame( 'publish', $row['status'] );
        $this->assertNull( $row['scheduled_at'] );
        $this->assertNotEmpty( get_post_meta( $this->id, Layout::META_PUBLISHED, true ) );
    }

    public function test_pending_review(): void {
        LayoutFactory::rest_update( $this->id, [ 'status' => 'pending' ] );
        $this->assertSame( 'pending', $this->listed_row()['status'] );
    }

    public function test_move_to_trash_moves_to_the_archive_tab(): void {
        $response = rest_do_request( new \WP_REST_Request( 'DELETE', '/wp/v2/' . Layout::POST_TYPE . '/' . $this->id ) );
        $this->assertSame( 200, $response->get_status() );

        $this->assertNull( $this->listed_row() );
        $this->assertSame( 'trash', $this->row( [ 'trash' ] )['status'] );
    }

    private function listed_row(): ?array {
        return $this->row( [ 'draft', 'pending', 'future', 'publish' ] );
    }

    private function row( array $statuses ): ?array {
        $request = new \WP_REST_Request( 'GET', '/yaycheckout/v1/layouts' );
        $request->set_param( 'status', $statuses );

        foreach ( rest_do_request( $request )->get_data()['items'] as $item ) {
            if ( $item['id'] === $this->id ) {
                return $item;
            }
        }

        return null;
    }
}
