<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Tests\Support\LayoutFactory;

/** Which blocks each editor offers: YayCheckout blocks only in layouts, WooCommerce's Checkout never in them. */
class BlockRegistryAllowedBlocksTest extends WP_UnitTestCase {

    private const WOO_CHECKOUT = 'woocommerce/checkout';

    private const NARROWED = [ 'core/paragraph', 'yaycheckout/checkout', self::WOO_CHECKOUT ];

    private bool $stubbed_woo_checkout = false;

    public function set_up(): void {
        parent::set_up();

        if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::WOO_CHECKOUT ) ) {
            register_block_type( self::WOO_CHECKOUT );
            $this->stubbed_woo_checkout = true;
        }
    }

    public function tear_down(): void {
        if ( $this->stubbed_woo_checkout ) {
            unregister_block_type( self::WOO_CHECKOUT );
        }

        parent::tear_down();
    }

    public function test_layout_editor_drops_woocommerce_checkout_from_a_narrowed_list(): void {
        $this->assertSame( [ 'core/paragraph', 'yaycheckout/checkout' ], $this->allowed( self::NARROWED, $this->layout_editor() ) );
    }

    public function test_layout_editor_drops_woocommerce_checkout_when_every_block_is_allowed(): void {
        $allowed = $this->allowed( true, $this->layout_editor() );

        $this->assertContains( 'yaycheckout/checkout', $allowed );
        $this->assertContains( 'core/paragraph', $allowed );
        $this->assertNotContains( self::WOO_CHECKOUT, $allowed );
    }

    public function test_layout_editor_keeps_a_list_without_woocommerce_checkout(): void {
        $this->assertSame( [ 'core/paragraph', 'yaycheckout/checkout' ], $this->allowed( [ 'core/paragraph', 'yaycheckout/checkout' ], $this->layout_editor() ) );
    }

    public function test_post_editor_drops_yaycheckout_blocks_from_a_narrowed_list(): void {
        $this->assertSame( [ 'core/paragraph', self::WOO_CHECKOUT ], $this->allowed( self::NARROWED, $this->post_editor() ) );
    }

    public function test_post_editor_drops_yaycheckout_blocks_when_every_block_is_allowed(): void {
        $allowed = $this->allowed( true, $this->post_editor() );

        $this->assertContains( 'core/paragraph', $allowed );
        $this->assertContains( self::WOO_CHECKOUT, $allowed );
        $this->assertSame( [], preg_grep( '#^yaycheckout/#', $allowed ) );
    }

    public function test_site_editor_drops_yaycheckout_blocks(): void {
        $context = new \WP_Block_Editor_Context( [ 'name' => 'core/edit-site' ] );

        $this->assertSame( [ 'core/paragraph', self::WOO_CHECKOUT ], $this->allowed( self::NARROWED, $context ) );
    }

    /** The context is unknown early in the layout editor too; stripping there would hide Checkout. */
    public function test_unknown_editor_is_left_alone(): void {
        $context = new \WP_Block_Editor_Context();

        $this->assertTrue( $this->allowed( true, $context ) );
        $this->assertSame( self::NARROWED, $this->allowed( self::NARROWED, $context ) );
    }

    /**
     * @param  bool|string[] $allowed
     * @return bool|string[]
     */
    private function allowed( $allowed, \WP_Block_Editor_Context $context ) {
        return BlockRegistry::get_instance()->restrict_to_layout_editor( $allowed, $context );
    }

    private function layout_editor(): \WP_Block_Editor_Context {
        return new \WP_Block_Editor_Context( [ 'post' => get_post( LayoutFactory::create_draft() ) ] );
    }

    private function post_editor(): \WP_Block_Editor_Context {
        return new \WP_Block_Editor_Context( [ 'post' => get_post( self::factory()->post->create() ) ] );
    }
}
