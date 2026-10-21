<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * The layout templates a merchant creates from, which ones this plan lists, and their files:
 * templates/layouts/<slug>/markup.php and design.json, or templates/layouts/pro/<slug>/ for a Pro
 * template (one built from Pro blocks). Free lists a Pro template as locked and ships only its
 * preview.html. A template missing either file is Coming soon.
 * preview.html, the picker's preview of the mock, sits beside them and availability ignores it,
 * as it does the optional logged-out.php, the template's own logged-out view.
 */
class LayoutTemplateCatalogue {

    /** Template 01, which a Default layout and a Reset without a usable template also get. */
    public const DEFAULT_SLUG = 'classic-two-column';

    /** A template's optional logged-out view, and the shared one beside the template folders. */
    private const LOGGED_OUT_FILE = 'logged-out.php';

    /**
     * Every template, for the picker. In Free a Pro template is listed with `available` false, so its
     * preview shows locked and it can't be created from.
     *
     * @return array{ slug: string, name: string, categories: string[], pro: bool, available: bool, preview_url: string }[]
     */
    public static function templates( bool $is_pro ): array {
        $templates = [];

        foreach ( self::catalogue() as $template ) {
            $template['pro']         = ! empty( $template['pro'] );
            $template['available']   = self::is_available( $template['slug'], $is_pro );
            $template['preview_url'] = self::preview_url( $template['slug'] );
            $templates[]             = $template;
        }

        return $templates;
    }

    /**
     * Whether $slug is in the catalogue, usable on the plan, and both of its files exist.
     *
     * @param bool|null $is_pro The plan to check against; null for the installed one, Plan::is_pro().
     */
    public static function is_available( string $slug, ?bool $is_pro = null ): bool {
        $entry = self::entry( $slug );

        // A Pro template on Free: its files are absent from the Free zip, and present in a dev tree without Pro booted.
        if ( null === $entry || ( ! empty( $entry['pro'] ) && ! ( $is_pro ?? Plan::is_pro() ) ) ) {
            return false;
        }

        return is_file( self::path( $slug, 'markup.php' ) ) && is_file( self::path( $slug, 'design.json' ) );
    }

    /**
     * Refuses a template this plan cannot build from. Shared by every route
     * that creates from a template so the plan rule has one owner.
     *
     * @throws \InvalidArgumentException When $slug is unknown, a Pro template on Free, or coming soon.
     */
    public static function assert_available( string $slug ): void {
        $is_pro   = Plan::is_pro();
        $template = array_column( self::templates( $is_pro ), null, 'slug' )[ $slug ] ?? null;

        if ( null === $template ) {
            throw new \InvalidArgumentException( esc_html__( 'Unknown template.', 'yaycheckout' ) );
        }

        if ( $template['pro'] && ! $is_pro ) {
            throw new \InvalidArgumentException( esc_html__( 'This template requires YayCheckout Pro.', 'yaycheckout' ) );
        }

        if ( ! $template['available'] ) {
            throw new \InvalidArgumentException( esc_html__( 'This template is coming soon.', 'yaycheckout' ) );
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
     * The logged-out view a layout from $slug starts with: the template's logged-out.php, or the shared
     * templates/layouts/logged-out.php when the template has none or $slug is not an available template.
     */
    public static function logged_out_markup( string $slug ): string {
        $file = self::is_available( $slug ) && is_file( self::path( $slug, self::LOGGED_OUT_FILE ) )
            ? self::path( $slug, self::LOGGED_OUT_FILE )
            : YAYCHECKOUT_PLUGIN_DIR . 'templates/layouts/' . self::LOGGED_OUT_FILE;

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    /** The template's name as the picker lists it, '' for an unknown slug. */
    public static function name( string $slug ): string {
        return self::entry( $slug )['name'] ?? '';
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
            throw new \InvalidArgumentException( esc_html( sprintf( 'Template "%s" is not available.', $slug ) ) );
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

    /** A template file's path inside the plugin, shared by its file path and its URL. Called only with catalogue slugs. */
    private static function relative_path( string $slug, string $name ): string {
        $pro = ! empty( self::entry( $slug )['pro'] ) ? 'pro/' : '';

        return 'templates/layouts/' . $pro . $slug . '/' . $name;
    }

    /** @return array{ slug: string, name: string, categories: string[], pro?: bool }|null */
    private static function entry( string $slug ): ?array {
        return array_column( self::catalogue(), null, 'slug' )[ $slug ] ?? null;
    }

    /**
     * Every template, in the picker's order. `pro` marks one built from Pro blocks: it lives under
     * templates/layouts/pro/, of which the Free zip keeps only each preview.html.
     *
     * @return array{ slug: string, name: string, categories: string[], pro?: bool }[]
     */
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
                'pro'        => true,
            ],
            [
                'slug'       => 'accordion-one-page',
                'name'       => 'Accordion one-page',
                'categories' => [ 'One column' ],
                'pro'        => true,
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
                'pro'        => true,
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
                'pro'        => true,
            ],
            [
                'slug'       => 'material-stepper',
                'name'       => 'Material stepper',
                'categories' => [ 'Multi-step', 'Two column' ],
                'pro'        => true,
            ],
            [
                'slug'       => 'digital-product',
                'name'       => 'Digital product',
                'categories' => [ 'Digital goods' ],
            ],
        ];
    }
}
