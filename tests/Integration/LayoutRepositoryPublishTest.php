<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Tests\Support\LayoutFactory;

/** A layout publishes only while it holds a Checkout block, at any depth. */
class LayoutRepositoryPublishTest extends WP_UnitTestCase {

    public function test_publishes_with_checkout_nested_in_a_group(): void {
        $id = LayoutFactory::create_draft(
            [ 'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:yaycheckout/checkout /--></div><!-- /wp:group -->' ]
        );

        $this->assertSame( 'publish', ( new LayoutRepository() )->publish( $id )->post_status );
    }

    public function test_publish_and_activate_refuse_a_layout_without_checkout(): void {
        $id         = LayoutFactory::create_draft( [ 'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ] );
        $repository = new LayoutRepository();

        foreach ( [ 'publish', 'activate' ] as $method ) {
            try {
                $repository->$method( $id );
                $this->fail( "$method should throw." );
            } catch ( \InvalidArgumentException $ex ) {
                $this->assertSame( 'draft', get_post( $id )->post_status, $method );
            }
        }
    }
}
