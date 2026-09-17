<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** A core wp/v2 publish whose markup the renderer could not serve is rejected before core saves. */
class RestPublishGuardTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        LayoutFactory::as_shop_manager();
    }

    public function test_publishing_unusable_markup_is_rejected(): void {
        $id = LayoutFactory::create_draft( [ 'post_content' => '' ] );

        $response = LayoutFactory::rest_update( $id, [ 'status' => 'publish' ] );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( 'yaycheckout_layout_markup_invalid', $response->get_data()['code'] );
        $this->assertSame( 'draft', get_post( $id )->post_status );
        $this->assertSame( [], get_post_meta( $id, Layout::META_PUBLISHED, false ) );
    }

    public function test_scheduling_unusable_markup_is_rejected(): void {
        $id = LayoutFactory::create_draft( [ 'post_content' => '' ] );

        // A past date makes core publish immediately, past the transition hook's ability to refuse.
        $response = LayoutFactory::rest_update(
            $id,
            [
                'status'   => 'future',
                'date_gmt' => gmdate( 'Y-m-d\TH:i:s', time() - HOUR_IN_SECONDS ),
            ]
        );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( 'yaycheckout_layout_markup_invalid', $response->get_data()['code'] );
        $this->assertSame( 'draft', get_post( $id )->post_status );
    }

    public function test_editing_unusable_markup_without_publishing_is_allowed(): void {
        $id = LayoutFactory::create_draft( [ 'post_content' => '' ] );

        $response = LayoutFactory::rest_update( $id, [ 'title' => 'Renamed' ] );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'Renamed', get_post( $id )->post_title );
    }

    public function test_publishing_valid_markup_passes(): void {
        $id = LayoutFactory::create_draft();

        $response = LayoutFactory::rest_update( $id, [ 'status' => 'publish' ] );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'publish', get_post( $id )->post_status );
    }
}
