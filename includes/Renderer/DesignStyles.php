<?php
namespace YayCheckout\Renderer;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Models\Design;
use YayCheckout\Repositories\DesignRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the Design page's tokens as `--yaycheckout-*` custom properties on
 * :root, followed by the merchant's custom CSS.
 *
 * A section left at theme default prints nothing, so the theme's own
 * WooCommerce styles apply.
 *
 * Values are printed unescaped: Design::from_option() only lets through hex
 * colours, enum members and in-range ints. Custom CSS is checked again here,
 * not only on save: the option can be written without going through REST.
 */
class DesignStyles {

    /** Behind a picked font, which is named, not loaded. Mirrored by the admin Preview (PreviewPane.tsx). */
    private const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

    /** Pixel height per inputs.size step. Mirrored by the admin Preview (PreviewPane.tsx). */
    private const INPUT_HEIGHTS = [
        'small'  => 32,
        'medium' => 40,
        'large'  => 48,
    ];

    public function enqueue(): void {
        wp_register_style( ScriptName::STYLE_CHECKOUT, false, [], YAYCHECKOUT_VERSION );
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT );
        wp_add_inline_style( ScriptName::STYLE_CHECKOUT, $this->css( ( new DesignRepository() )->get() ) );
    }

    public function css( Design $design ): string {
        $vars = $this->vars( $design->tokens );
        $css  = '';

        if ( $vars ) {
            $css = ":root {\n";
            foreach ( $vars as $name => $value ) {
                $css .= "\t--yaycheckout-{$name}: {$value};\n";
            }
            $css .= "}\n";
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
                // ponytail: serif fonts fall back to sans when not loaded; add a generic family per font once Google Fonts are enqueued.
                $vars['font-family'] = '"' . Design::FONT_FAMILIES[ $typography['font_family'] ] . '", ' . self::FONT_FALLBACK;
            }

            $vars['font-size-base'] = $typography['base_size'] . 'px';
        }

        if ( $tokens['spacing']['enabled'] ) {
            // %F, not a (string) cast: PHP 7 casts floats per LC_NUMERIC, so a de_DE host would print 1,5.
            $vars['spacing-scale'] = rtrim( rtrim( sprintf( '%.1F', $tokens['spacing']['scale'] ), '0' ), '.' );
        }

        if ( $tokens['buttons']['enabled'] ) {
            $vars['button-radius'] = $tokens['buttons']['radius'] . 'px';
        }

        if ( $tokens['inputs']['enabled'] ) {
            $vars['input-radius'] = $tokens['inputs']['radius'] . 'px';
            $vars['input-height'] = self::INPUT_HEIGHTS[ $tokens['inputs']['size'] ] . 'px';
        }

        return $vars;
    }
}
