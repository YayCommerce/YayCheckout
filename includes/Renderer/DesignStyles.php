<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\LayoutDesign;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the layout's design as `--yaycheckout-*` custom properties on :root, then the page rule, then the
 * site-wide custom CSS and the layout's. An empty token prints nothing: checkout-base.css and the blocks' CSS
 * read each property with a neutral fallback that follows the theme.
 * inputs.label is the one token with no property here: it switches a layout of rules on, so it is a class on the
 * checkout wrapper (checkout/render.php, and checkout/edit.tsx on the canvas).
 *
 * The page rule, with a font the heading rule, and with a link colour the link rules are the only selectors printed
 * here. The page rule sets the
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

    /**
     * The links in paragraphs, headings and the terms, which take the link colour instead of the theme's. A link
     * inside a block with a link colour of its own (core's has-link-color, on the block or a group around it) is
     * left out, and so is a button. Printed only with a colour: the theme's own link colour is no custom property a
     * fallback could read.
     */
    private const LINK_SELECTOR = 'body.yaycheckout-page :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button), .editor-styles-wrapper :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button)';

    /** The same links, hovered or focused. */
    private const LINK_HOVER_SELECTOR = 'body.yaycheckout-page :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button):is(:hover, :focus), .editor-styles-wrapper :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button):is(:hover, :focus)';

    /** Page property => the token it takes, printed only when vars() set that token. */
    private const PAGE_PROPERTIES = [
        'background-color' => 'color-background',
        'background-image' => 'background-gradient',
        'color'            => 'color-text',
        'font-family'      => 'font-family',
        'font-size'        => 'font-size-base',
    ];

    /** The page's font size in px while typography.base_size is empty, so a layout doesn't take the theme's. Mirrored by design-css.ts. */
    public const DEFAULT_BASE_SIZE = 13;

    /**
     * Per inputs.size step: the field's height, then its height with floating labels. In em, so a field grows with
     * the layout's font. Mirrored by the layout editor (design-css.ts).
     */
    private const INPUT_SIZES = [
        'small'  => [ '2.5em', '3em' ],
        'medium' => [ '2.75em', '3.25em' ],
        'large'  => [ '3em', '3.5em' ],
    ];

    /** Per buttons.font_weight step: its CSS weight. Mirrored by the layout editor (design-css.ts). */
    private const BUTTON_FONT_WEIGHTS = [
        'normal'   => '400',
        'medium'   => '500',
        'semibold' => '600',
        'bold'     => '700',
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

        if ( isset( $vars['link-color'] ) ) {
            $css .= self::LINK_SELECTOR . " {\n\tcolor: var(--yaycheckout-link-color);\n}\n";
        }

        // An empty hover colour keeps the link colour, so the theme's hover colour doesn't show either.
        if ( isset( $vars['link-color'] ) || isset( $vars['link-hover-color'] ) ) {
            $css .= self::LINK_HOVER_SELECTOR . " {\n\tcolor: var(--yaycheckout-link-hover-color, var(--yaycheckout-link-color));\n}\n";
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

    /** Custom property values by name (without the `--yaycheckout-` prefix), for the tokens that aren't empty, and always the font size. */
    private function vars( array $sections ): array {
        $colours    = $sections['colours'];
        $typography = $sections['typography'];
        $inputs     = $sections['inputs'];
        $background = $colours['background'];
        $not_empty  = static function ( $value ) {
            return '' !== $value;
        };

        // A gradient fills only the page: inputs and rows read color-background as a colour, so they keep their fallback.
        $vars = array_filter(
            [
                'color-primary' => $colours['primary'],
                'color-text'    => $colours['text'],
                LayoutDesign::is_gradient( $background ) ? 'background-gradient' : 'color-background' => $background,
                'color-border'  => $colours['border'],
                'color-muted'   => $colours['muted'],
            ],
            $not_empty
        );

        if ( isset( LayoutDesign::FONT_STACKS[ $typography['font_family'] ] ) ) {
            $vars['font-family'] = LayoutDesign::FONT_STACKS[ $typography['font_family'] ];
        } elseif ( '' !== $typography['font_family'] ) {
            $fallback            = in_array( $typography['font_family'], LayoutDesign::SERIF_FONTS, true ) ? self::SERIF_FONT_FALLBACK : self::FONT_FALLBACK;
            $vars['font-family'] = '"' . LayoutDesign::FONT_FAMILIES[ $typography['font_family'] ] . '", ' . $fallback;
        }

        $vars['font-size-base'] = ( $typography['base_size'] ?? self::DEFAULT_BASE_SIZE ) . 'px';

        $is_underline = 'underline' === $inputs['style'];

        if ( $is_underline ) {
            $vars['input-radius'] = '0px';
        } elseif ( null !== $inputs['radius'] ) {
            $vars['input-radius'] = $inputs['radius'] . 'px';
        }

        // The box is the chosen height: no vertical padding for the text to push past it.
        if ( '' !== $inputs['size'] ) {
            $vars['input-height']          = self::INPUT_SIZES[ $inputs['size'] ][0];
            $vars['input-floating-height'] = self::INPUT_SIZES[ $inputs['size'] ][1];
            $vars['input-padding-block']   = '0';
        }

        if ( '' !== $inputs['style'] ) {
            $vars['input-border-width'] = $is_underline ? '0 0 1px' : '1px';
            // An underline input with a background, or one on focus, is a filled box, so its text keeps the side padding.
            $is_filled                    = '' !== $inputs['background'] || '' !== $inputs['focus_background'];
            $vars['input-padding-inline'] = $is_underline && ! $is_filled ? '0' : '12px';
        }

        $vars = array_merge(
            $vars,
            array_filter(
                [
                    'input-background'       => $inputs['background'],
                    'input-color'            => $inputs['text_color'],
                    'input-label-color'      => $inputs['label_color'],
                    'input-border-color'     => $inputs['border'],
                    'input-focus-color'      => $inputs['focus_border'],
                    'input-focus-background' => $inputs['focus_background'],
                ],
                $not_empty
            )
        );

        // Thin border drops the flush ring, so focus just recolours the border.
        if ( 'border' === $inputs['focus_style'] ) {
            $vars['input-focus-ring-width'] = '0';
        }

        // A radius of 0 is a square box, not an empty token.
        $checkboxes = $sections['checkboxes'];

        if ( null !== $checkboxes['size'] ) {
            $vars['checkbox-size'] = $checkboxes['size'] . 'px';
        }

        if ( null !== $checkboxes['radius'] ) {
            $vars['checkbox-radius'] = $checkboxes['radius'] . 'px';
        }

        if ( null !== $checkboxes['border_width'] ) {
            $vars['checkbox-border-width'] = $checkboxes['border_width'] . 'px';
        }

        $vars = array_merge(
            $vars,
            array_filter(
                [
                    'checkbox-border-color'  => $checkboxes['border'],
                    'checkbox-background'    => $checkboxes['background'],
                    'checkbox-checked-color' => $checkboxes['checked'],
                ],
                $not_empty
            )
        );

        $radios = $sections['radios'];

        if ( null !== $radios['size'] ) {
            $vars['radio-size'] = $radios['size'] . 'px';
        }

        if ( null !== $radios['border_width'] ) {
            $vars['radio-border-width'] = $radios['border_width'] . 'px';
        }

        $vars = array_merge(
            $vars,
            array_filter(
                [
                    'radio-border-color'  => $radios['border'],
                    'radio-background'    => $radios['background'],
                    'radio-checked-color' => $radios['checked'],
                ],
                $not_empty
            )
        );

        $vars = array_merge(
            $vars,
            array_filter(
                [
                    'link-color'       => $colours['link'],
                    'link-hover-color' => $colours['link_hover'],
                ],
                $not_empty
            )
        );

        $help_text = $sections['help_text'];

        if ( null !== $help_text['size'] ) {
            $vars['help-text-size'] = $help_text['size'] . 'px';
        }

        $buttons = $sections['buttons'];
        $vars    = array_merge(
            $vars,
            array_filter(
                [
                    'button-background'       => $buttons['background'],
                    'button-color'            => $buttons['text'],
                    'button-hover-background' => $buttons['hover_background'],
                    'button-hover-color'      => $buttons['hover_text'],
                ],
                $not_empty
            )
        );

        // A hover colour of its own replaces the darkened default.
        if ( '' !== $buttons['hover_background'] || '' !== $buttons['hover_text'] ) {
            $vars['button-hover-filter'] = 'none';
        }

        if ( null !== $buttons['radius'] ) {
            $vars['button-radius'] = $buttons['radius'] . 'px';
        }

        if ( '' !== $buttons['font_weight'] ) {
            $vars['button-font-weight'] = self::BUTTON_FONT_WEIGHTS[ $buttons['font_weight'] ];
        }

        if ( '' !== $buttons['text_transform'] ) {
            $vars['button-text-transform'] = $buttons['text_transform'];
        }

        return $vars;
    }
}
