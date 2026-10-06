<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Builds block markup in PHP as parse_blocks()-shaped arrays, serialized by core so attribute
 * JSON is escaped the way the editor expects. Use it, not string literals, for server-side markup.
 */
class BlockMarkup {

    /**
     * One block, rendered by its render.php with no static inner HTML. innerContent holds one
     * null per inner block: core places each child there when serializing, and a count mismatch drops children.
     *
     * @param  array   $attrs        Block attributes; [] serializes as none.
     * @param  array[] $inner_blocks Children built by this method.
     */
    public static function block( string $name, array $attrs = [], array $inner_blocks = [] ): array {
        return [
            'blockName'    => $name,
            'attrs'        => $attrs,
            'innerBlocks'  => $inner_blocks,
            'innerHTML'    => '',
            'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
        ];
    }

    /**
     * A static container (core Group, Columns, Column) whose saved HTML is $open . $close, children between.
     * Pass HTML exactly as the core block's save() prints it for these attributes, already escaped,
     * or the editor flags the block as invalid.
     *
     * @param  array   $attrs        Block attributes; [] serializes as none.
     * @param  string  $open         Saved opening tag, e.g. '<div class="wp-block-group">'.
     * @param  string  $close        Saved closing tag.
     * @param  array[] $inner_blocks Children built by this class.
     */
    public static function wrapper( string $name, array $attrs, string $open, string $close, array $inner_blocks = [] ): array {
        return [
            'blockName'    => $name,
            'attrs'        => $attrs,
            'innerBlocks'  => $inner_blocks,
            'innerHTML'    => $open . $close,
            'innerContent' => array_merge( [ $open ], array_fill( 0, count( $inner_blocks ), null ), [ $close ] ),
        ];
    }

    /**
     * A static leaf (core Paragraph) with saved HTML. Same contract as wrapper(): $html must match
     * the core block's save() output for these attributes, already escaped.
     *
     * @param  array  $attrs Block attributes; [] serializes as none.
     * @param  string $html  Saved HTML.
     */
    public static function html( string $name, array $attrs, string $html ): array {
        return [
            'blockName'    => $name,
            'attrs'        => $attrs,
            'innerBlocks'  => [],
            'innerHTML'    => $html,
            'innerContent' => [ $html ],
        ];
    }

    /**
     * The markup for a tree of block() arrays, ready for post_content.
     * Pass it through wp_slash() before wp_insert_post(): unslashing strips
     * the \uXXXX escapes core puts in attribute JSON.
     *
     * @param array[] $blocks Top-level blocks.
     */
    public static function serialize( array $blocks ): string {
        return serialize_blocks( $blocks );
    }
}
