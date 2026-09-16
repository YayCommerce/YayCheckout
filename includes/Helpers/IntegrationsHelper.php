<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Which companion Yay plugins are present.
 *
 * Core knows the companions it can work with and reports whether each one
 * is loaded. Delivered to the SPA through the localize bootstrap — it is a
 * fact about this page load, so there is no REST route to refetch it.
 * Companions that later declare checkout slots can extend their own entry
 * through the filter.
 */
class IntegrationsHelper {

    /** Filter over the integration list; entries follow the shape describe() emits. */
    public const FILTER = 'yaycheckout_integrations';

    /**
     * Wire shape consumed by the SPA — snake_case, mirrored by integrationWireSchema.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_integrations(): array {
        $list = [
            self::describe(
                'yayboost',
                __( 'YayBoost', 'yaycheckout' ),
                __( 'Order bumps you can place as a block.', 'yaycheckout' ),
                'YAYBOOST_VERSION'
            ),
            self::describe(
                'yayloyalty',
                __( 'YayLoyalty', 'yaycheckout' ),
                __( 'Points balance and redemption at checkout.', 'yaycheckout' ),
                'YAYLOYALTY_VERSION'
            ),
        ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- FILTER is a yaycheckout_ prefixed constant.
        return (array) apply_filters( self::FILTER, $list );
    }

    private static function describe( string $slug, string $name, string $description, string $version_const ): array {
        // A version constant only exists once the companion's main file ran,
        // so "detected" and "active" are the same fact today. Both are emitted
        // because a filter-added integration may be installed-but-inactive.
        $detected = defined( $version_const );

        return [
            'slug'           => $slug,
            'name'           => $name,
            'description'    => $description,
            'detected'       => $detected,
            'version'        => $detected ? (string) constant( $version_const ) : null,
            'active'         => $detected,
            'learn_more_url' => YAYCHECKOUT_DOCS_URL,
        ];
    }
}
