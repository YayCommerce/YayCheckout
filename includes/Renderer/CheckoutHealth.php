<?php
namespace YayCheckout\Renderer;

use YayCheckout\Helpers\GatewayCompatibilityHelper;
use YayCheckout\Helpers\PluginHeaderHelper;
use YayCheckout\Helpers\StoreEnvironmentHelper;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\CompatibilityCatalogueRepository;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Support\CompatibilityCatalogue;

defined( 'ABSPATH' ) || exit;

/**
 * What makes a layout unsafe to serve, as a reason the merchant can read, or ''. Reads store
 * configuration only (layout markup, active plugins, enabled gateways, the cached catalogue),
 * never the cart or the shopper's input, so the render, AJAX and submit requests of one form agree.
 */
class CheckoutHealth {

    public static function problem( Layout $layout ): string {
        $own = self::layout_problem( $layout );

        return '' !== $own ? $own : self::store_problem();
    }

    /**
     * What the admin reports: the same rules the storefront applies, safe mode on or off. Nothing
     * while no layout is active, since the storefront then serves WooCommerce's checkout anyway.
     */
    public static function reported_problem(): string {
        $layouts = ( new LayoutRepository() )->active_layouts();
        if ( [] === $layouts ) {
            return '';
        }

        // Pro: the shopper decides which active layout serves, so any one of them can be the broken one.
        foreach ( $layouts as $layout ) {
            $own = self::layout_problem( $layout );
            if ( '' !== $own ) {
                return $own;
            }
        }

        return self::store_problem();
    }

    /** The layout's own problem: its last render failed with this content, or its markup may not be published. */
    private static function layout_problem( Layout $layout ): string {
        $failure = CheckoutPage::render_failure( $layout );

        return '' !== $failure ? $failure : LayoutMarkupReader::publish_error( $layout->content );
    }

    /** A problem with the store rather than one layout. A new rule is one private method and one line here. */
    public static function store_problem(): string {
        $catalogue = ( new CompatibilityCatalogueRepository() )->cached();

        $problem = self::conflicting_plugin( $catalogue );
        if ( '' === $problem ) {
            $problem = self::unsupported_payment( $catalogue );
        }

        /**
         * A reason the checkout layout is unsafe to serve, or ''. With safe mode on, a non-empty
         * reason gives the checkout back to WooCommerce. Read on every checkout request: keep it cheap
         * and base it on store configuration, never the cart.
         *
         * @param string $problem The reason YayCheckout found, or ''.
         */
        return (string) apply_filters( 'yaycheckout_safe_mode_problem', $problem );
    }

    /** The first active plugin the catalogue marks as conflicting, unless the installed version is at or past its fix. */
    private static function conflicting_plugin( array $catalogue ): string {
        $files = (array) get_option( 'active_plugins', [] );
        if ( is_multisite() ) {
            $files = array_merge( $files, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
        }

        $active = [];
        foreach ( $files as $file ) {
            // Slug => path PluginHeaderHelper reads: the directory, or the file itself for a single-file plugin.
            $path = '.' === dirname( $file ) ? $file : dirname( $file );
            // Lowercased as CheckoutHookScanHelper::owner() does, so a directory like YayBoost still matches its catalogue slug.
            $active[ strtolower( basename( $path, '.php' ) ) ] = WP_PLUGIN_DIR . '/' . $path;
        }

        foreach ( $catalogue['plugins'] ?? [] as $slug => $entry ) {
            if ( CompatibilityCatalogue::STATUS_CONFLICT !== ( $entry['status'] ?? null ) || ! isset( $active[ $slug ] ) ) {
                continue;
            }

            $fixed_in = (string) ( $entry['fixed_in'] ?? '' );
            // The header is read only when needed: get_plugin_data() opens the plugin's files.
            $header = '' !== $fixed_in || null === $entry['title'] ? PluginHeaderHelper::describe( 'plugin', $slug, $active[ $slug ] ) : null;
            if ( '' !== $fixed_in && ! version_compare( $header['version'], $fixed_in, '<' ) ) {
                continue;
            }

            $name = $entry['title'] ?? $header['name'];

            /* translators: %s: plugin name. */
            return sprintf( __( '%s conflicts with YayCheckout.', 'yaycheckout' ), $name );
        }

        return '';
    }

    /**
     * No enabled classic gateway the layout can offer, while the native checkout has one. A store
     * with no payment method at all is not reported: WooCommerce's checkout could not take payment either.
     */
    private static function unsupported_payment( array $catalogue ): string {
        $classic = WC()->payment_gateways()->payment_gateways();

        $usable_native = 0;
        foreach ( $classic as $gateway ) {
            if ( 'yes' !== $gateway->enabled ) {
                continue;
            }

            // One gateway the layout can offer is enough; the block checkout lookup below is only for the rest.
            if ( CompatibilityCatalogue::STATUS_CONFLICT !== ( $catalogue['gateways'][ $gateway->id ]['status'] ?? null ) ) {
                return '';
            }
            ++$usable_native;
        }

        if ( StoreEnvironmentHelper::STORE_TYPE_BLOCK === StoreEnvironmentHelper::store_type() ) {
            $usable_native += count( array_diff( GatewayCompatibilityHelper::block_registered_ids(), array_keys( $classic ) ) );
        }

        return $usable_native > 0
            ? __( 'No enabled payment method works in the YayCheckout checkout.', 'yaycheckout' )
            : '';
    }
}
