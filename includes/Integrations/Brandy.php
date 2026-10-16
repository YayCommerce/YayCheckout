<?php
namespace YayCheckout\Integrations;

use YayCheckout\Helpers\CheckoutHookScanHelper;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the Brandy theme off a layout checkout, active only while Brandy is the theme.
 *
 * Brandy's stylesheets, scripts, inline CSS and theme.json styled the layout's inputs, buttons,
 * headings and fonts over its design. Where a layout takes over, the page loads none of the
 * theme's assets, and global styles come from a bundled theme.json, Twenty Twenty-Five's,
 * in place of Brandy's, keeping only Brandy's fonts. Brandy Blocks' assets stay.
 */
class Brandy {

    use SingletonTrait;

    /** Hooks where Brandy queues, prints or rewrites assets; its callbacks come off these. */
    private const ASSET_HOOKS = [
        'wp_enqueue_scripts',
        'enqueue_block_assets',
        'wp_head',
        'wp_print_styles',
        'wp_print_scripts',
        'wp_print_footer_scripts',
        'wp_footer',
        'style_loader_tag',
        'script_loader_tag',
        'script_loader_src',
    ];

    /** The theme's inline-only handles: they have no src under the theme to match. Brandy Blocks prefixes its own `brandy-blocks/`, so it stays. */
    private const INLINE_HANDLES = [ 'brandy-global-style-variables', 'brandy-ios-input-zoom-fix' ];

    /** Prefix of the theme's block-settings inline handles. */
    private const INLINE_PREFIX = 'brandy/';

    /** Twenty Twenty-Five's theme.json without its templates or fonts: the presets, layout sizes and element styles blocks expect from a theme. */
    private const THEME_JSON = 'assets/theme-json/checkout.json';

    /** A font family preset in a style value, in theme.json's form or as the CSS variable: Brandy's niches write both. */
    private const FONT_PRESET = '/^(?:var:preset\|font-family\||var\(--wp--preset--font-family--)([a-z0-9-]+)\)?$/';

    protected function __construct() {
        // After CheckoutPage decides at 10 whether a layout takes over.
        add_action( 'wp', [ $this, 'maybe_isolate' ], 11 );
    }

    public function maybe_isolate(): void {
        $checkout = CheckoutPage::get_instance();
        // Read here, not in the constructor: plugins load before the theme defines it.
        if ( ! defined( 'BRANDY_VERSION' ) || ! $checkout->takes_over() ) {
            return;
        }

        /**
         * Whether a layout checkout drops the Brandy theme's assets and theme.json styles.
         *
         * Return false to keep Brandy's stylesheets, scripts, inline CSS and global styles on the checkout.
         *
         * @param bool                       $isolate Default true.
         * @param \YayCheckout\Models\Layout $layout  The layout rendering the checkout.
         */
        if ( ! apply_filters( 'yaycheckout_brandy_isolation', true, $checkout->layout() ) ) {
            return;
        }

        $this->remove_theme_callbacks();

        // Catches what the theme queues outside those hooks, such as Brandy's render_block enqueues.
        add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_theme_assets' ], PHP_INT_MAX );
        add_action( 'wp_print_footer_scripts', [ $this, 'dequeue_theme_assets' ], 1 );

        // Last, so Brandy's niche and its other filters have merged into the data it reads the fonts from.
        add_filter( 'wp_theme_json_data_theme', [ $this, 'checkout_theme_json' ], PHP_INT_MAX );
        add_filter( 'wp_theme_json_data_user', [ $this, 'empty_user_json' ] );
        // Block registration may already have resolved this request's theme.json.
        wp_clean_theme_json_cache();
    }

    /** Removes every callback a theme file added to the asset hooks. */
    private function remove_theme_callbacks(): void {
        global $wp_filter;

        $dirs = [ wp_normalize_path( get_template_directory() ), wp_normalize_path( get_stylesheet_directory() ) ];

        foreach ( self::ASSET_HOOKS as $hook ) {
            if ( ! isset( $wp_filter[ $hook ] ) ) {
                continue;
            }

            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    $file = CheckoutHookScanHelper::callback_file( $callback['function'] );
                    if ( null !== $file && $this->in_theme( wp_normalize_path( $file ), $dirs ) ) {
                        remove_filter( $hook, $callback['function'], $priority );
                    }
                }
            }
        }
    }

    public function dequeue_theme_assets(): void {
        $roots = [];
        foreach ( [ get_template_directory_uri(), get_stylesheet_directory_uri() ] as $url ) {
            $roots[] = $url;
            // A src may also be root-relative.
            $roots[] = (string) wp_parse_url( $url, PHP_URL_PATH );
        }

        foreach ( [ wp_styles(), wp_scripts() ] as $deps ) {
            foreach ( $deps->queue as $handle ) {
                $src = isset( $deps->registered[ $handle ] ) ? (string) $deps->registered[ $handle ]->src : '';

                if ( $this->in_theme( $src, $roots ) || in_array( $handle, self::INLINE_HANDLES, true ) || 0 === strpos( $handle, self::INLINE_PREFIX ) ) {
                    $deps->dequeue( $handle );
                }
            }
        }
    }

    /**
     * Replaces Brandy's theme.json with the bundled one, keeping Brandy's body and heading fonts.
     *
     * The font is part of a Brandy niche's look: each niche's niche.json sets its own, merged into the theme data
     * before this runs. A `file:./` font src still resolves against Brandy's folder, the active theme's,
     * and Brandy Sites rewrites its own fonts' paths to its plugin.
     *
     * @param \WP_Theme_JSON_Data $theme_json Brandy's theme.json, with the child theme and the current niche merged in.
     */
    public function checkout_theme_json( $theme_json ): \WP_Theme_JSON_Data {
        $data = wp_json_file_decode( YAYCHECKOUT_PLUGIN_DIR . self::THEME_JSON, [ 'associative' => true ] );
        if ( ! is_array( $data ) ) {
            $data = [ 'version' => \WP_Theme_JSON::LATEST_SCHEMA ];
        }

        $brandy   = $theme_json->get_data();
        $families = $brandy['settings']['typography']['fontFamilies'] ?? [];
        // Unflattened while the filters run: the presets sit under their origin.
        $families = array_column( $families['theme'] ?? $families, null, 'slug' );
        $targets  = [
            [ 'styles', 'typography', 'fontFamily' ],
            [ 'styles', 'elements', 'heading', 'typography', 'fontFamily' ],
        ];

        foreach ( $targets as $path ) {
            $font = _wp_array_get( $brandy, $path, '' );
            if ( ! is_string( $font ) || '' === $font ) {
                continue;
            }

            if ( preg_match( self::FONT_PRESET, $font, $match ) ) {
                if ( ! isset( $families[ $match[1] ] ) ) {
                    continue;
                }
                $this->put_family( $data, $families[ $match[1] ] );
            }

            _wp_array_set( $data, $path, $font );
        }

        return new \WP_Theme_JSON_Data( $data, 'theme' );
    }

    /** Adds a font family preset to the bundled data, replacing one with the same slug. */
    private function put_family( array &$data, array $family ): void {
        $list = $data['settings']['typography']['fontFamilies'] ?? [];
        $list = array_values(
            array_filter(
                $list,
                static function ( array $existing ) use ( $family ): bool {
                    return ( $existing['slug'] ?? '' ) !== $family['slug'];
                }
            )
        );

        $list[] = $family;

        $data['settings']['typography']['fontFamilies'] = $list;
    }

    /** Replaces the Site Editor's styles for Brandy with empty ones. */
    public function empty_user_json(): \WP_Theme_JSON_Data {
        return new \WP_Theme_JSON_Data( [ 'version' => \WP_Theme_JSON::LATEST_SCHEMA ], 'custom' );
    }

    /** @param string[] $roots Theme directories, URLs or URL paths, with forward slashes. */
    private function in_theme( string $path, array $roots ): bool {
        if ( '' === $path ) {
            return false;
        }

        foreach ( $roots as $root ) {
            if ( '' !== $root && 0 === strpos( $path, trailingslashit( $root ) ) ) {
                return true;
            }
        }

        return false;
    }
}
