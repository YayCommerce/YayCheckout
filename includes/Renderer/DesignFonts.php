<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Repositories\FontRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The `@font-face` rules of a layout's picked font, from its Font Library upload (FontRepository), for the
 * storefront checkout and the layout editor canvas. Prints nothing when the theme or the site's global styles
 * load the font already, since core prints its faces then, or when it isn't uploaded. Never uploads: that
 * happens when the layout's design is saved (Engine\DesignFontInstall), so a page view never downloads.
 */
class DesignFonts {

    private FontRepository $fonts;

    public function __construct() {
        $this->fonts = new FontRepository();
    }

    /** @param string $slug A LayoutDesign::FONT_FAMILIES key; '' prints nothing. */
    public function css( string $slug ): string {
        if ( '' === $slug || ! isset( LayoutDesign::FONT_FAMILIES[ $slug ] ) || $this->fonts->is_provided_by_site( $slug ) ) {
            return '';
        }

        return $this->faces_css( LayoutDesign::FONT_FAMILIES[ $slug ], $this->fonts->faces( $slug ) );
    }

    /**
     * The faces of a font that isn't uploaded yet, at Google's own URLs, so the layout editor can show it before the
     * save uploads it. Editor only: the storefront prints css(), so shoppers never load fonts from Google.
     *
     * @param string $slug A LayoutDesign::FONT_FAMILIES key other than ''.
     */
    public function preview_css( string $slug ): string {
        return $this->faces_css( LayoutDesign::FONT_FAMILIES[ $slug ], $this->fonts->remote_faces( $slug ) );
    }

    /** @param array<int, array{style: string, weight: string, url: string}> $faces */
    private function faces_css( string $name, array $faces ): string {
        $css = '';

        foreach ( $faces as $face ) {
            // The style and weight come from Font Library posts, which anyone with edit_theme_options can write.
            $style  = preg_match( '/^(normal|italic|oblique)$/', $face['style'] ) ? $face['style'] : 'normal';
            $weight = preg_match( '/^\d{3}( \d{3})?$/', $face['weight'] ) ? $face['weight'] : '400';
            $url    = esc_url_raw( $face['url'] );
            $format = 'woff2' === strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ? " format('woff2')" : '';

            if ( '' === $url ) {
                continue;
            }

            $css .= "@font-face {\n\tfont-family: \"{$name}\";\n\tfont-style: {$style};\n\tfont-weight: {$weight};\n\tfont-display: fallback;\n\tsrc: url('{$url}'){$format};\n}\n";
        }

        return $css;
    }
}
