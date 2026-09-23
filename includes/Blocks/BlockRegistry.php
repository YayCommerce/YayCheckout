<?php
namespace YayCheckout\Blocks;

use YayCheckout\Models\Layout;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor blocks and their category.
 *
 * Registers from the built assets/dist/blocks/<block>/ directory, so deps and
 * render callback come from build output, not a second PHP declaration.
 * Which editors offer the blocks is gated here; storefront rendering is not.
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
        'extra-field',
        'shipping-methods',
        'express-pay',
        'payment',
        'order-summary',
        'place-order',
        'slot',
    ];

    protected function __construct() {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_filter( 'block_categories_all', [ $this, 'register_category' ], 10, 2 );
        // After other plugins so a later true/list cannot put our blocks back.
        add_filter( 'allowed_block_types_all', [ $this, 'restrict_to_layout_editor' ], 999, 2 );
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

    /**
     * Adds the YayCheckout inserter category on the layout CPT only.
     */
    public function register_category( $categories, $editor_context ) {
        if ( ! $this->is_layout_editor_context( $editor_context ) ) {
            return $categories;
        }

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

    /**
     * Keeps YayCheckout blocks in the layout editor and WooCommerce's Checkout
     * block out of it.
     *
     * WooCommerce's Checkout would be a second checkout form inside the
     * layout's own. See docs/system-architecture.md, blocks.
     * Types stay registered so checkout-page.php can still do_blocks() the layout.
     * Unknown context is left alone: stripping then hid Checkout from the layout editor.
     */
    public function restrict_to_layout_editor( $allowed_block_types, $editor_context ) {
        if ( $this->is_layout_editor_context( $editor_context ) ) {
            return self::without( $allowed_block_types, [ 'woocommerce/checkout' ] );
        }

        if ( $this->is_known_non_layout_editor( $editor_context ) ) {
            return self::without( $allowed_block_types, $this->block_names() );
        }

        return $allowed_block_types;
    }

    /**
     * @param  bool|string[] $allowed_block_types
     * @return bool|string[]
     */
    private static function without( $allowed_block_types, array $names ) {
        if ( true === $allowed_block_types ) {
            // true means "all"; subtract by naming every registered type.
            $allowed_block_types = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
        }

        if ( ! is_array( $allowed_block_types ) ) {
            return $allowed_block_types;
        }

        return array_values( array_diff( $allowed_block_types, $names ) );
    }

    private function is_layout_editor_context( $editor_context ): bool {
        return Layout::POST_TYPE === $this->editor_post_type( $editor_context );
    }

    private function is_known_non_layout_editor( $editor_context ): bool {
        $post_type = $this->editor_post_type( $editor_context );

        if ( '' !== $post_type ) {
            return Layout::POST_TYPE !== $post_type;
        }

        $name = $editor_context->name ?? '';

        return in_array( $name, [ 'core/edit-site', 'core/edit-widgets', 'core/customize-widgets' ], true );
    }

    /**
     * Post type of this editor, or '' when Gutenberg has not attached one yet.
     */
    private function editor_post_type( $editor_context ): string {
        $post = $editor_context->post ?? null;

        if ( $post instanceof \WP_Post && '' !== $post->post_type ) {
            return $post->post_type;
        }

        if ( ! function_exists( 'get_current_screen' ) ) {
            return '';
        }

        $screen = get_current_screen();

        if ( ! $screen || ! is_string( $screen->post_type ) || '' === $screen->post_type ) {
            return '';
        }

        return $screen->post_type;
    }

    private function block_names(): array {
        $names = [];

        foreach ( self::BLOCKS as $slug ) {
            $names[] = 'yaycheckout/' . $slug;
        }

        return $names;
    }
}
