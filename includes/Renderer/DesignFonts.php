<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Repositories\FontRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The `@font-face` rules of a layout's picked font, from its Font Library install (FontRepository), for the
 * storefront checkout and the layout editor canvas. Prints nothing when the theme or the site's global styles
 * load the font already, since core prints its faces then, or when it isn't installed. Never installs:
 * the editor installs a font when it is picked, and the storefront must not download one on a page view.
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

        $name = LayoutDesign::FONT_FAMILIES[ $slug ];
        $css  = '';

        foreach ( $this->fonts->faces( $slug ) as $face ) {
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
