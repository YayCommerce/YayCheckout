<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

class LayoutRepositoryQueryTest extends WP_UnitTestCase {

    private LayoutRepository $repository;

    public function set_up(): void {
        parent::set_up();
        $this->repository = new LayoutRepository();
    }

    /** @return array<string, int> post_status => id */
    private function one_of_each_status(): array {
        $ids = [
            'draft'   => LayoutFactory::create_draft( [ 'post_title' => 'Draft' ] ),
            'pending' => LayoutFactory::create_draft(
                [
                    'post_title'  => 'Pending',
                    'post_status' => 'pending',
                ]
            ),
            'future'  => LayoutFactory::create_scheduled( [ 'post_title' => 'Scheduled' ] ),
            'publish' => LayoutFactory::create_draft( [ 'post_title' => 'Published' ] ),
            'trash'   => LayoutFactory::create_draft( [ 'post_title' => 'Archived' ] ),
        ];

        $this->repository->publish( $ids['publish'] );
        wp_trash_post( $ids['trash'] );

        return $ids;
    }

    public function test_listed_excludes_only_the_trash(): void {
        $this->one_of_each_status();

        $this->assertSame( 4, $this->repository->query( LayoutQuery::listed() )['total'] );
        $this->assertSame( 1, $this->repository->query( LayoutQuery::archived() )['total'] );
        $this->assertSame( 5, $this->repository->query( LayoutQuery::any() )['total'] );
        $this->assertSame( 4, $this->repository->count() );
        $this->assertSame(
            [
                'listed'   => 4,
                'archived' => 1,
                'active'   => 0,
            ],
            $this->repository->counts()
        );
    }

    public function test_search_matches_the_title_only(): void {
        LayoutFactory::create_draft( [ 'post_title' => 'Needle in title' ] );
        LayoutFactory::create_draft(
            [
                'post_title'   => 'Other',
                'post_content' => '<!-- wp:yaycheckout/checkout --><!-- wp:paragraph --><p>needle</p><!-- /wp:paragraph --><!-- /wp:yaycheckout/checkout -->',
            ]
        );

        $result = $this->repository->query( LayoutQuery::listed()->with_search( 'needle' ) );

        $this->assertSame( 1, $result['total'] );
        $this->assertSame( 'Needle in title', $result['items'][0]->title );
    }

    public function test_pages_are_fixed_size_newest_first(): void {
        $ids = [];
        for ( $i = 0; $i < LayoutQuery::PER_PAGE + 1; $i++ ) {
            $ids[] = LayoutFactory::create_draft();
        }

        $first  = $this->repository->query( LayoutQuery::listed() );
        $second = $this->repository->query( LayoutQuery::listed()->with_pagination( 2 ) );

        $this->assertCount( LayoutQuery::PER_PAGE, $first['items'] );
        $this->assertSame( 2, $first['total_pages'] );
        $this->assertCount( 1, $second['items'] );
        $this->assertSame( $ids[0], $second['items'][0]->id );
    }

    public function test_statuses_are_post_status_values(): void {
        $ids = $this->one_of_each_status();

        $result = $this->repository->query( LayoutQuery::of_statuses( [ 'future' ] ) );

        $this->assertSame( 1, $result['total'] );
        $this->assertSame( $ids['future'], $result['items'][0]->id );
    }
}
