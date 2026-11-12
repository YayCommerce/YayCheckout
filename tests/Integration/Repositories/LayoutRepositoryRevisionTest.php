<?php
namespace YayCheckout\Tests\Integration\Repositories;

use WP_UnitTestCase;
use YayCheckout\Controllers\LayoutTemplateCatalogue;
use YayCheckout\Models\Layout;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Repositories\LayoutRepository;

/** A layout starts with a revision holding its design, so the editor can always go back to how it was created. */
class LayoutRepositoryRevisionTest extends WP_UnitTestCase {

    private const SLUG = LayoutTemplateCatalogue::DEFAULT_SLUG;

    /** Core's tear_down() unregisters every meta key, and revisions copy only registered revisioned ones. */
    public function set_up(): void {
        parent::set_up();
        LayoutPostType::get_instance()->register();
    }

    /** Newest first. By ID: a test saves them all within one second, so their dates tie. */
    private function revisions( int $id ): array {
        return array_values(
            wp_get_post_revisions(
                $id,
                [
                    'orderby' => 'ID',
                    'order'   => 'DESC',
                ]
            )
        );
    }

    public function test_create_saves_first_revision_with_design(): void {
        $id = ( new LayoutRepository() )->create( 'Test layout', self::SLUG )->id;

        $revisions = $this->revisions( $id );

        $this->assertCount( 1, $revisions );
        $this->assertSame( get_post( $id )->post_content, $revisions[0]->post_content );
        $this->assertSame(
            LayoutTemplateCatalogue::design( self::SLUG )->to_meta(),
            get_post_meta( $revisions[0]->ID, Layout::META_DESIGN, true )
        );
    }

    public function test_reset_keeps_changed_design_as_revision(): void {
        $repository = new LayoutRepository();
        $id         = $repository->create( 'Test layout', self::SLUG )->id;
        $template   = LayoutTemplateCatalogue::design( self::SLUG )->to_meta();
        $changed    = $template;

        $changed['colours']['primary'] = '#123456';
        wp_update_post(
            wp_slash(
                [
                    'ID'         => $id,
                    'meta_input' => [ Layout::META_DESIGN => $changed ],
                ]
            )
        );

        $repository->reset( $id );

        $designs = array_map(
            function ( \WP_Post $revision ) {
                return get_post_meta( $revision->ID, Layout::META_DESIGN, true );
            },
            $this->revisions( $id )
        );

        $this->assertSame( $template, get_post_meta( $id, Layout::META_DESIGN, true ) );
        $this->assertSame( [ $template, $changed, $template ], $designs );
    }
}
