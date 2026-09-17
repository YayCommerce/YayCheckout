<?php
namespace YayCheckout\Blocks;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers YayCheckout editor blocks and their category.
 *
 * Blocks are built by packages/blocks (@wordpress/scripts) into
 * assets/dist/blocks/<block>/, which holds block.json, index.js, render.php and
 * index.asset.php. register_block_type() reads that directory, so script
 * dependencies and the render callback come from build output rather than being
 * declared a second time in PHP.
 */
class BlockRegistry {

    use SingletonTrait;

    public const CATEGORY = 'yaycheckout';

    /** Built block directories, relative to assets/dist/blocks/. */
    private const BLOCKS = [
        'checkout',
        'contact',
        'billing',
        'shipping',
        'field',
    ];

    protected function __construct() {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_filter( 'block_categories_all', [ $this, 'register_category' ] );
    }

    public function register_blocks() {
        foreach ( self::BLOCKS as $block ) {
            $dir = YAYCHECKOUT_PLUGIN_DIR . 'assets/dist/blocks/' . $block;

            /*
             * Build output is gitignored, so a source checkout that has not run
             * `pnpm build:blocks` has no block.json here. Skip rather than let
             * register_block_type() emit a doing_it_wrong notice on every request.
             */
            if ( ! file_exists( $dir . '/block.json' ) ) {
                continue;
            }

            register_block_type( $dir );
        }
    }

    /**
     * @param  array $categories
     * @return array
     */
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
