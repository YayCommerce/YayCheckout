<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Builds block markup in PHP: parsed-block arrays in the shape parse_blocks()
 * returns, serialized by core so attribute JSON is escaped the way the editor
 * expects. Use it wherever markup is authored server-side — seeds, library
 * templates, Pro presets — instead of string literals.
 */
class BlockMarkup {

    /**
     * One block, rendered by its render.php with no static inner HTML.
     *
     * The innerContent entries are one null per inner block: that is how core places
     * each child when serializing, and a count mismatch drops children.
     *
     * @param  array   $attrs        Block attributes; [] serializes as none.
     * @param  array[] $inner_blocks Children built by this method.
     * @return array   Parsed-block array.
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
