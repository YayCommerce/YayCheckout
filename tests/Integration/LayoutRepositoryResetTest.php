<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** Reset swaps in the starting markup and leaves the old markup recoverable from revisions. */
class LayoutRepositoryResetTest extends WP_UnitTestCase {

    private const ORIGINAL = '<!-- wp:yaycheckout/checkout --><!-- wp:paragraph --><p>ORIGINAL</p><!-- /wp:paragraph --><!-- /wp:yaycheckout/checkout -->';

    /** A layout inserted by create, duplicate or import has no revision until the editor saves it. */
    public function test_keeps_the_old_markup_of_a_layout_never_saved_in_the_editor(): void {
        $id = LayoutFactory::create_draft( [ 'post_content' => self::ORIGINAL ] );
        $this->assertSame( [], wp_get_post_revisions( $id ) );

        $layout = ( new LayoutRepository() )->reset( $id );

        $this->assertSame( LayoutRepository::default_markup(), $layout->content );
        $this->assertContains( self::ORIGINAL, wp_list_pluck( wp_get_post_revisions( $id ), 'post_content' ) );
    }

    public function test_keeps_title_and_status(): void {
        $id = LayoutFactory::create_draft(
            [
                'post_title'   => 'Summer checkout',
                'post_status'  => 'publish',
                'post_content' => self::ORIGINAL,
            ]
        );

        $layout = ( new LayoutRepository() )->reset( $id );

        $this->assertSame( 'Summer checkout', $layout->title );
        $this->assertSame( 'publish', $layout->post_status );
    }

    public function test_refuses_a_missing_layout(): void {
        $this->expectException( \InvalidArgumentException::class );

        ( new LayoutRepository() )->reset( PHP_INT_MAX );
    }
}
