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
}
