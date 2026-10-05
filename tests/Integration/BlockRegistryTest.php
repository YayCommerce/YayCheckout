<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Tests\Support\LayoutFactory;

/** Block registration and the YayCheckout inserter category. */
class BlockRegistryTest extends WP_UnitTestCase {

    public function test_every_built_block_is_registered(): void {
        $built      = array_map(
            static fn( $metadata ) => 'yaycheckout/' . basename( dirname( $metadata ) ),
            glob( YAYCHECKOUT_PLUGIN_DIR . 'assets/dist/blocks/*/block.json' )
        );
        $registered = preg_grep( '#^yaycheckout/#', array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) );

        sort( $built );
        sort( $registered );

        $this->assertNotEmpty( $built );
        $this->assertSame( $built, $registered );
    }

    /** The inserter lists blocks in registration order, so they follow the checkout from top to bottom. */
    public function test_blocks_register_in_checkout_order(): void {
        $registered = array_values( preg_grep( '#^yaycheckout/#', array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) ) );

        $in_checkout_order = [ 'checkout', 'contact', 'billing', 'shipping', 'field', 'order-review', 'extra-field', 'shipping-methods', 'express-pay', 'payment', 'coupon-form', 'order-summary', 'place-order', 'slot' ];

        $this->assertSame( array_map( static fn( $slug ) => 'yaycheckout/' . $slug, $in_checkout_order ), $registered );
    }

    public function test_category_is_added_in_the_layout_editor_only(): void {
        $categories = [ [ 'slug' => 'text' ] ];
        $layout     = new \WP_Block_Editor_Context( [ 'post' => get_post( LayoutFactory::create_draft() ) ] );
        $post       = new \WP_Block_Editor_Context( [ 'post' => get_post( self::factory()->post->create() ) ] );

        $this->assertSame( [ 'yaycheckout', 'text' ], array_column( BlockRegistry::get_instance()->register_category( $categories, $layout ), 'slug' ) );
        $this->assertSame( $categories, BlockRegistry::get_instance()->register_category( $categories, $post ) );
    }
}
