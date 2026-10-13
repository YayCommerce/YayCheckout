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
        // Its last render failed with this content, or its markup may not be published, or the store has a problem.
        $own = CheckoutPage::render_failure( $layout );
        if ( '' === $own ) {
            $own = LayoutMarkupReader::publish_error( $layout->content );
        }

        return '' !== $own ? $own : self::store_problem();
    }

    /**
     * What the admin reports: every problem the storefront's rules find, safe mode on or off. A layout's
     * own problems come first, one per active layout, each naming it; a store-level problem follows and
     * belongs to no layout. A render failure reads as one plain sentence, its recorded message, which may be a
     * PHP error, kept as `detail` for support; `detail` is null otherwise. Empty while no layout is active,
     * since the storefront then serves WooCommerce's checkout anyway.
     *
     * @return array<int, array{reason: string, detail: ?string, layout_id: ?int, layout_title: ?string}>
     */
    public static function reported_problems(): array {
        $layouts = ( new LayoutRepository() )->active_layouts();
        if ( [] === $layouts ) {
            return [];
        }

        $problems = [];

        // Pro: the shopper decides which active layout serves, so any one of them can be the broken one.
        foreach ( $layouts as $layout ) {
            $failure = CheckoutPage::render_failure( $layout );
            $own     = '' !== $failure
                ? __( 'This layout failed to load on the checkout page.', 'yaycheckout' )
                : LayoutMarkupReader::publish_error( $layout->content );
            if ( '' !== $own ) {
                $problems[] = [
                    'reason'       => $own,
                    'detail'       => '' !== $failure ? $failure : null,
                    'layout_id'    => $layout->id,
                    'layout_title' => $layout->title,
                ];
            }
        }

        $store = self::store_problem();
        if ( '' !== $store ) {
            $problems[] = [
                'reason'       => $store,
                'detail'       => null,
                'layout_id'    => null,
                'layout_title' => null,
            ];
        }

        return $problems;
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

            // The header is read only when needed: get_plugin_data() opens the plugin's files.
            $header = null !== ( $entry['fixed_in'] ?? null ) || null === $entry['title'] ? PluginHeaderHelper::describe( 'plugin', $slug, $active[ $slug ] ) : null;
            if ( ! CompatibilityCatalogue::conflicts( $entry, $header['version'] ?? '' ) ) {
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
