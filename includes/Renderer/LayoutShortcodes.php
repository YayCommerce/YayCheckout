<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a layout's blocks with shortcodes run in its Shortcode and text blocks. The layout is never
 * passed through `the_content`, where core runs shortcodes, and core's Shortcode block only adds
 * paragraphs. Only BLOCKS run them: our blocks print values the shopper typed, which must stay text.
 * A Paragraph's live value is filled on render_block_core/paragraph, which core runs after render_block,
 * so a shopper's value never reaches this pass either.
 */
final class LayoutShortcodes {

    /**
     * Blocks whose rendered HTML runs shortcodes. One holding inner blocks (a List item with a nested list)
     * is skipped, since its inner blocks ran already: a shortcode never runs twice.
     */
    public const BLOCKS = [ 'core/shortcode', 'core/paragraph', 'core/heading', 'core/html', 'core/list-item' ];

    /** Just before BlockVisibility, so it marks the shortcode's own markup. */
    private const PRIORITY = BlockVisibility::RENDER_PRIORITY - 1;

    /** Renders $content as do_blocks() does, with shortcodes run in BLOCKS. */
    public static function render( string $content ): string {
        add_filter( 'render_block', [ self::class, 'run_in_block' ], self::PRIORITY, 2 );

        try {
            return do_blocks( $content );
        } finally {
            remove_filter( 'render_block', [ self::class, 'run_in_block' ], self::PRIORITY );
        }
    }

    /**
     * On render_block, while render() runs. Untyped: another filter may have returned anything.
     *
     * @param mixed $block_content The block's rendered HTML.
     * @param mixed $block         The parsed block.
     */
    public static function run_in_block( $block_content, $block ): string {
        $block_content = (string) $block_content;
        $name          = is_array( $block ) ? ( $block['blockName'] ?? '' ) : '';

        // The name first: a container's content is the whole page below it. No '[' means no shortcode, which
        // skips shortcode_unautop()'s regex over every registered tag.
        if ( ! in_array( $name, self::BLOCKS, true ) || ! empty( $block['innerBlocks'] ) || false === strpos( $block_content, '[' ) ) {
            return $block_content;
        }

        $html = do_shortcode( shortcode_unautop( $block_content ) );

        // Core's Shortcode block prints no wrapper; one gives Visibility a tag to mark, whatever the shortcode prints.
        return 'core/shortcode' === $name ? '<div class="wp-block-shortcode">' . $html . '</div>' : $html;
    }
}
