<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the Design page's tokens as `--yaycheckout-*` custom properties on
 * :root, then the page rule, then the merchant's custom CSS. A section left at
 * theme default prints nothing: checkout-base.css and the blocks' CSS read each
 * property with a neutral fallback that follows the theme.
 *
 * The page rule is the only selector printed here. It sets the page's background,
 * text colour and font, which a stylesheet can't read from an unset property
 * without resetting the theme's own (background isn't inherited, and an unset
 * inherited property on body takes html's value, not the theme's body value).
 *
 * Values are printed unescaped: Design::from_option() only lets through hex
 * colours, enum members and in-range ints. Custom CSS is checked again here,
 * not only on save: the option can be written without going through REST.
 */
class DesignStyles {

    /** Behind a picked font, which is named, not loaded. Mirrored by the admin Preview (PreviewPane.tsx). */
    private const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

    /** The storefront page and the layout editor canvas. */
    private const PAGE_SELECTOR = 'body.yaycheckout-page, .editor-styles-wrapper';

    /** Pixel height per inputs.size step. Mirrored by the admin Preview (PreviewPane.tsx). */
    private const INPUT_HEIGHTS = [
        'small'  => 32,
        'medium' => 40,
        'large'  => 48,
    ];

    /** @param bool $with_custom_css False in the layout editor, where the merchant's storefront CSS could break the canvas. */
    public function css( Design $design, bool $with_custom_css = true ): string {
        $vars = $this->vars( $design->tokens );
        $page = $this->page_declarations( $design->tokens );
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

        $custom_css = Design::sanitize_custom_css( $design->custom_css );

        if ( $custom_css instanceof \WP_Error || '' === trim( $custom_css ) ) {
            return $css;
        }

        return $css . $custom_css . "\n";
    }

    /** Custom property values by name (without the `--yaycheckout-` prefix), for the sections that are on. */
    private function vars( array $tokens ): array {
        $typography = $tokens['typography'];
        $vars       = [];

        if ( $tokens['colours']['enabled'] ) {
            $vars += [
                'color-primary'    => $tokens['colours']['primary'],
                'color-text'       => $tokens['colours']['text'],
                'color-background' => $tokens['colours']['background'],
                'color-border'     => $tokens['colours']['border'],
            ];
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

    /** Page property => token name, for the sections that are on. */
    private function page_declarations( array $tokens ): array {
        $page = [];

        if ( $tokens['colours']['enabled'] ) {
            $page['background-color'] = 'color-background';
            $page['color']            = 'color-text';
        }

        if ( $tokens['typography']['enabled'] ) {
            if ( '' !== $tokens['typography']['font_family'] ) {
                $page['font-family'] = 'font-family';
            }

            $page['font-size'] = 'font-size-base';
        }

        return $page;
    }
}
