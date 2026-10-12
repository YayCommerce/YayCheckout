<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * The arrow glyphs of the Continue, Back and Return to Cart blocks, from each block's `arrow` attribute.
 *
 * Mirrored by packages/blocks/src/shared/button-arrow.ts for the editor; change both together.
 */
final class ButtonArrow {

    /** Direction => style => glyph. Styles mirror the blocks' block.json `arrow` enum. */
    private const GLYPHS = [
        'back'    => [
            'arrow'   => '←',
            'chevron' => '‹',
        ],
        'forward' => [
            'arrow'   => '→',
            'chevron' => '›',
        ],
    ];

    /**
     * The glyph for $style pointing $direction; empty for `none` or any unknown style or direction.
     *
     * @param string $style     `none`, `arrow` or `chevron`.
     * @param string $direction `back` or `forward`.
     */
    public static function glyph( string $style, string $direction ): string {
        return self::GLYPHS[ $direction ][ $style ] ?? '';
    }

    /**
     * The glyph in an escaped span hidden from screen readers, from the block's `arrow` attribute; empty when there is none.
     * A layout saved before the block had the attribute has no value, so it gets no arrow.
     *
     * @param array  $attributes Block attributes.
     * @param string $direction  `back` or `forward`.
     * @param string $class_name The span's class.
     */
    public static function html( array $attributes, string $direction, string $class_name ): string {
        $glyph = self::glyph( (string) ( $attributes['arrow'] ?? 'none' ), $direction );

        return '' !== $glyph ? '<span class="' . esc_attr( $class_name ) . '" aria-hidden="true">' . esc_html( $glyph ) . '</span>' : '';
    }
}
