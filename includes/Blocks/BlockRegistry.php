<?php
namespace YayCheckout\Blocks;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor blocks and their category.
 *
 * Registers from the built assets/dist/blocks/<block>/ directory, so deps and
 * render callback come from build output, not a second PHP declaration.
 */
class BlockRegistry {

    use SingletonTrait;

    public const CATEGORY = 'yaycheckout';

    /** Relative to assets/dist/blocks/. */
    private const BLOCKS = [
        'checkout',
        'contact',
        'billing',
        'shipping',
        'field',
        'order-review',
    ];

    protected function __construct() {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_filter( 'block_categories_all', [ $this, 'register_category' ] );
    }

    public function register_blocks() {
        foreach ( self::BLOCKS as $block ) {
            $dir = YAYCHECKOUT_PLUGIN_DIR . 'assets/dist/blocks/' . $block;

            // Unbuilt checkout: skip rather than emit doing_it_wrong on every request.
            if ( ! file_exists( $dir . '/block.json' ) ) {
                continue;
            }

            register_block_type( $dir );
        }
    }

    public function register_category( $categories ) {
        return array_merge(
            [
                [
                    'slug'  => self::CATEGORY,
                    'title' => __( 'YayCheckout', 'yaycheckout' ),
                    'icon'  => null,
                ],
            ],
            $categories
        );
    }
}
