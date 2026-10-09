<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Design;
use YayCheckout\Models\LayoutDesign;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the Design page's tokens, with the layout's overridden sections swapped in,
 * as `--yaycheckout-*` custom properties on :root, then the page rule, then the
 * global custom CSS and the layout's. A section left at
 * theme default prints nothing: checkout-base.css and the blocks' CSS read each
 * property with a neutral fallback that follows the theme.
 *
 * The page rule is the only selector printed here. It sets the page's background,
 * text colour and font, which a stylesheet can't read from an unset property
 * without resetting the theme's own (background isn't inherited, and an unset
 * inherited property on body takes html's value, not the theme's body value).
 *
 * Values are printed unescaped: Design::from_option() only lets through hex
 * colours, enum members and in-range ints, and LayoutDesign::from_meta() projects the same
 * way. Each custom CSS is checked here, not only on save: the option and the meta can be
 * written without going through REST, and one rejected CSS must not drop the other.
 */
class DesignStyles {

    /** Behind a picked font, which is named, not loaded. Mirrored by the admin Preview (PreviewPane.tsx) and the layout editor (design-css.ts). */
    private const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

    /** The storefront page and the layout editor canvas. */
    private const PAGE_SELECTOR = 'body.yaycheckout-page, .editor-styles-wrapper';

    /** Page property => the token it takes, printed only when vars() set that token. */
    private const PAGE_PROPERTIES = [
        'background-color' => 'color-background',
        'background-image' => 'background-gradient',
        'color'            => 'color-text',
        'font-family'      => 'font-family',
        'font-size'        => 'font-size-base',
    ];

    /** Pixel height per inputs.size step. Mirrored by the admin Preview (PreviewPane.tsx) and the layout editor (design-css.ts). */
    private const INPUT_HEIGHTS = [
        'small'  => 32,
        'medium' => 40,
        'large'  => 48,
    ];

    /**
     * The design as one stylesheet. Everything before the custom CSS is mirrored by the layout
     * editor (design-css.ts), which reprints it as the merchant edits the layout's override.
     *
     * @param bool $with_custom_css False in the layout editor, where the merchant's storefront CSS could break the canvas.
     */
    public function css( Design $design, LayoutDesign $layout, bool $with_custom_css ): string {
        $vars = $this->vars( $layout->tokens_over( $design->tokens ) );
        $page = array_intersect( self::PAGE_PROPERTIES, array_keys( $vars ) );
        $css  = '';

        if ( $vars ) {
            $css = ":root {\n";
            foreach ( $vars as $name => $value ) {
                $css .= "\t--yaycheckout-{$name}: {$value};\n";
            }
            $css .= "}\n";
        }

        if ( $page ) {
            $css .= self::PAGE_SELECTOR . " {\n";
            foreach ( $page as $property => $name ) {
                $css .= "\t{$property}: var(--yaycheckout-{$name});\n";
            }
            $css .= "}\n";
        }

        if ( ! $with_custom_css ) {
            return $css;
        }

        foreach ( [ $design->custom_css, $layout->custom_css ] as $custom_css ) {
            $custom_css = Design::sanitize_custom_css( $custom_css );

            if ( ! $custom_css instanceof \WP_Error && '' !== trim( $custom_css ) ) {
                $css .= $custom_css . "\n";
            }
        }

        return $css;
    }

    /** Custom property values by name (without the `--yaycheckout-` prefix), for the sections that are on. */
    private function vars( array $tokens ): array {
        $typography = $tokens['typography'];
        $vars       = [];

        if ( $tokens['colours']['enabled'] ) {
            $background = $tokens['colours']['background'];

            // A gradient fills only the page: inputs and rows read color-background as a colour, so they keep their
            // fallback. A layout's unset ('') colour prints nothing, so it follows the theme.
            $vars += array_filter(
                [
                    'color-primary' => $tokens['colours']['primary'],
                    'color-text'    => $tokens['colours']['text'],
                    Design::is_gradient( $background ) ? 'background-gradient' : 'color-background' => $background,
                    'color-border'  => $tokens['colours']['border'],
                ],
                static function ( $value ) {
                    return '' !== $value;
                }
            );
        }

        if ( $typography['enabled'] ) {
            if ( '' !== $typography['font_family'] ) {
                // TODO(fonts): a serif font falls back to sans when not loaded; add a generic family per font once the fonts are enqueued, which nothing does yet.
                $vars['font-family'] = '"' . Design::FONT_FAMILIES[ $typography['font_family'] ] . '", ' . self::FONT_FALLBACK;
            }

            $vars['font-size-base'] = $typography['base_size'] . 'px';
        }

        if ( $tokens['inputs']['enabled'] ) {
            $is_underline = 'underline' === $tokens['inputs']['style'];

            $vars['input-radius']         = $is_underline ? '0px' : $tokens['inputs']['radius'] . 'px';
            $vars['input-height']         = self::INPUT_HEIGHTS[ $tokens['inputs']['size'] ] . 'px';
            $vars['input-border-width']   = $is_underline ? '0 0 1px' : '1px';
            $vars['input-padding-inline'] = $is_underline ? '0' : '12px';
        }

        return $vars;
    }
}
