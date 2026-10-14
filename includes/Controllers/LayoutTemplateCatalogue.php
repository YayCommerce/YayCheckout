<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * The layout templates a merchant creates from, which ones this plan unlocks, and their files:
 * templates/layouts/<slug>/markup.php and design.json. A template missing either is Coming soon.
 * preview.html, the picker's preview of the mock, sits beside them and availability ignores it.
 */
class LayoutTemplateCatalogue {

    /** Template 01, which a Default layout and a Reset without a usable template also get. */
    public const DEFAULT_SLUG = 'classic-two-column';

    /** Free picks the first N; the rest render locked. */
    private const FREE_UNLOCKED_COUNT = 3;

    /** @return array{ slug: string, name: string, categories: string[], locked: bool, available: bool, preview_url: string }[] */
    public static function templates( bool $is_pro ): array {
        $templates = self::catalogue();

        foreach ( $templates as $index => &$template ) {
            $template['locked']      = ! $is_pro && $index >= self::FREE_UNLOCKED_COUNT;
            $template['available']   = self::is_available( $template['slug'] );
            $template['preview_url'] = self::preview_url( $template['slug'] );
        }

        return $templates;
    }

    /** Whether $slug is in the catalogue and both of its files exist. */
    public static function is_available( string $slug ): bool {
        if ( ! in_array( $slug, array_column( self::catalogue(), 'slug' ), true ) ) {
            return false;
        }

        return is_file( self::path( $slug, 'markup.php' ) ) && is_file( self::path( $slug, 'design.json' ) );
    }

    /**
     * Refuses a template this plan cannot build from. Shared by every route
     * that creates from a template so the lock rule has one owner.
     *
     * @throws \InvalidArgumentException When $slug is unknown, locked on this plan or coming soon.
     */
    public static function assert_available( string $slug ): void {
        $template = array_column( self::templates( Plan::is_pro() ), null, 'slug' )[ $slug ] ?? null;

        if ( null === $template ) {
            throw new \InvalidArgumentException( __( 'Unknown template.', 'yaycheckout' ) );
        }

        if ( $template['locked'] ) {
            throw new \InvalidArgumentException( __( 'This template is part of YayCheckout Pro.', 'yaycheckout' ) );
        }

        if ( ! $template['available'] ) {
            throw new \InvalidArgumentException( __( 'This template is coming soon.', 'yaycheckout' ) );
        }
    }

    /**
     * The template's block markup, as its markup.php prints it.
     *
     * @throws \InvalidArgumentException When $slug is not an available template.
     */
    public static function markup( string $slug ): string {
        $file = self::file( $slug, 'markup.php' );

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    /**
     * The template's design, from its design.json projected through LayoutDesign::schema().
     *
     * @throws \InvalidArgumentException When $slug is not an available template.
     */
    public static function design( string $slug ): LayoutDesign {
        return LayoutDesign::from_meta( wp_json_file_decode( self::file( $slug, 'design.json' ), [ 'associative' => true ] ) );
    }

    /**
     * The path of one of the template's files. Guards every file lookup, so only catalogue slugs ever reach a path.
     *
     * @throws \InvalidArgumentException When $slug is not an available template.
     */
    private static function file( string $slug, string $name ): string {
        if ( ! self::is_available( $slug ) ) {
            throw new \InvalidArgumentException( sprintf( 'Template "%s" is not available.', $slug ) );
        }

        return self::path( $slug, $name );
    }

    /** The URL of the template's preview.html, or '' when it has none. Called only with catalogue slugs. */
    private static function preview_url( string $slug ): string {
        if ( ! is_file( self::path( $slug, 'preview.html' ) ) ) {
            return '';
        }

        return YAYCHECKOUT_PLUGIN_URL . self::relative_path( $slug, 'preview.html' ) . '?ver=' . YAYCHECKOUT_VERSION;
    }

    /** Unchecked: callers check $slug against the catalogue first. */
    private static function path( string $slug, string $name ): string {
        return YAYCHECKOUT_PLUGIN_DIR . self::relative_path( $slug, $name );
    }

    /** A template file's path inside the plugin, shared by its file path and its URL. */
    private static function relative_path( string $slug, string $name ): string {
        return 'templates/layouts/' . $slug . '/' . $name;
    }

    /** @return array{ slug: string, name: string, categories: string[] }[] */
    private static function catalogue(): array {
        return [
            [
                'slug'       => 'classic-two-column',
                'name'       => 'Classic two-column',
                'categories' => [ 'Two column' ],
            ],
            [
                'slug'       => 'split-express',
                'name'       => 'Split express',
                'categories' => [ 'Two column' ],
            ],
            [
                'slug'       => 'editorial-fashion',
                'name'       => 'Editorial fashion',
                'categories' => [ 'Two column' ],
            ],
            [
                'slug'       => 'multi-step-wizard',
                'name'       => 'Multi-step wizard',
                'categories' => [ 'Multi-step', 'One column' ],
            ],
            [
                'slug'       => 'accordion-one-page',
                'name'       => 'Accordion one-page',
                'categories' => [ 'One column' ],
            ],
            [
                'slug'       => 'upsell-cross-sell',
                'name'       => 'Upsell & cross-sell',
                'categories' => [ 'Two column' ],
            ],
            [
                'slug'       => 'minimal-steps',
                'name'       => 'Minimal steps',
                'categories' => [ 'Multi-step', 'One column' ],
            ],
            [
                'slug'       => 'live-receipt',
                'name'       => 'Live receipt',
                'categories' => [ 'Two column' ],
            ],
            [
                'slug'       => 'soft-steps',
                'name'       => 'Soft steps',
                'categories' => [ 'Multi-step', 'One column' ],
            ],
            [
                'slug'       => 'material-stepper',
                'name'       => 'Material stepper',
                'categories' => [ 'Two column' ],
            ],
            [
                'slug'       => 'digital-product',
                'name'       => 'Digital product',
                'categories' => [ 'Digital goods' ],
            ],
        ];
    }
}
