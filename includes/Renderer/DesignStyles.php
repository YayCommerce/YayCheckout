<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\LayoutDesign;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the layout's design as `--yaycheckout-*` custom properties on :root, then the page rule, then the
 * site-wide custom CSS and the layout's. An empty token prints nothing: checkout-base.css and the blocks' CSS
 * read each property with a neutral fallback that follows the theme.
 *
 * The page rule and, with a font, the heading rule are the only selectors printed here. The page rule sets the
 * page's background, text colour and font, which a stylesheet can't read from an unset property
 * without resetting the theme's own (background isn't inherited, and an unset
 * inherited property on body takes html's value, not the theme's body value).
 *
 * Values are printed unescaped: LayoutDesign::from_meta() only lets through colours and gradients its
 * patterns accept, enum members and in-range ints. Each custom CSS is checked here, not only on save: the
 * setting and the meta can be written without going through REST, and one rejected CSS must not drop the other.
 */
class DesignStyles {

    /**
     * Behind a picked font, for while it loads or when it couldn't be installed. Mirrored by the layout editor
     * (design-css.ts).
     */
    private const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

    /** Behind a LayoutDesign::SERIF_FONTS font, the same way. */
    private const SERIF_FONT_FALLBACK = 'Georgia, "Times New Roman", serif';

    /** The storefront page and the layout editor canvas. */
    private const PAGE_SELECTOR = 'body.yaycheckout-page, .editor-styles-wrapper';

    /**
     * The checkout's headings, which take the page's font instead of the theme's heading font. `inherit`, so a
     * block's own font (its has-*-font-family class) still reaches the headings inside it, and a heading with a
     * font of its own is left out.
     */
    private const HEADING_SELECTOR = 'body.yaycheckout-page :is(h1, h2, h3, h4, h5, h6):not([class*="-font-family"]), .editor-styles-wrapper :is(h1, h2, h3, h4, h5, h6):not([class*="-font-family"])';

    /** Page property => the token it takes, printed only when vars() set that token. */
    private const PAGE_PROPERTIES = [
        'background-color' => 'color-background',
        'background-image' => 'background-gradient',
        'color'            => 'color-text',
        'font-family'      => 'font-family',
        'font-size'        => 'font-size-base',
    ];

    /** Pixel height per inputs.size step. Mirrored by the layout editor (design-css.ts). */
    private const INPUT_HEIGHTS = [
        'small'  => 32,
        'medium' => 40,
        'large'  => 48,
    ];

    /**
     * The design as one stylesheet. Everything before the custom CSS is mirrored by the layout
     * editor (design-css.ts), which reprints it as the merchant edits the layout's design.
     *
     * @param string $site_css        The site-wide custom CSS (Settings → Advanced), printed before the layout's.
     * @param bool   $with_custom_css False in the layout editor, where the merchant's storefront CSS could break the canvas.
     */
    public function css( LayoutDesign $design, string $site_css, bool $with_custom_css ): string {
        $vars = $this->vars( $design->sections );
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

        if ( isset( $vars['font-family'] ) ) {
            $css .= self::HEADING_SELECTOR . " {\n\tfont-family: inherit;\n}\n";
        }

        if ( ! $with_custom_css ) {
            return $css;
        }

        foreach ( [ $site_css, $design->custom_css ] as $custom_css ) {
            $custom_css = LayoutDesign::sanitize_custom_css( $custom_css );

            if ( ! $custom_css instanceof \WP_Error && '' !== trim( $custom_css ) ) {
                $css .= $custom_css . "\n";
            }
        }

        return $css;
    }

    /** Custom property values by name (without the `--yaycheckout-` prefix), for the tokens that aren't empty. */
    private function vars( array $sections ): array {
        $colours    = $sections['colours'];
        $typography = $sections['typography'];
        $inputs     = $sections['inputs'];
        $background = $colours['background'];

        // A gradient fills only the page: inputs and rows read color-background as a colour, so they keep their fallback.
        $vars = array_filter(
            [
                'color-primary' => $colours['primary'],
                'color-text'    => $colours['text'],
                LayoutDesign::is_gradient( $background ) ? 'background-gradient' : 'color-background' => $background,
                'color-border'  => $colours['border'],
            ],
            static function ( $value ) {
                return '' !== $value;
            }
        );

        if ( '' !== $typography['font_family'] ) {
            $fallback            = in_array( $typography['font_family'], LayoutDesign::SERIF_FONTS, true ) ? self::SERIF_FONT_FALLBACK : self::FONT_FALLBACK;
            $vars['font-family'] = '"' . LayoutDesign::FONT_FAMILIES[ $typography['font_family'] ] . '", ' . $fallback;
        }

        if ( null !== $typography['base_size'] ) {
            $vars['font-size-base'] = $typography['base_size'] . 'px';
        }

        $is_underline = 'underline' === $inputs['style'];

        if ( $is_underline ) {
            $vars['input-radius'] = '0px';
        } elseif ( null !== $inputs['radius'] ) {
            $vars['input-radius'] = $inputs['radius'] . 'px';
        }

        if ( '' !== $inputs['size'] ) {
            $vars['input-height'] = self::INPUT_HEIGHTS[ $inputs['size'] ] . 'px';
        }

        if ( '' !== $inputs['style'] ) {
            $vars['input-border-width']   = $is_underline ? '0 0 1px' : '1px';
            $vars['input-padding-inline'] = $is_underline ? '0' : '12px';
        }

        return $vars;
    }
}
