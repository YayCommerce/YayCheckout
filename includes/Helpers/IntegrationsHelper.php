<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Which companion Yay plugins are loaded. Localized, not a REST route: it is
 * a fact about this page load.
 */
class IntegrationsHelper {

    /** Entries follow the shape describe() emits. */
    public const FILTER = 'yaycheckout_integrations';

    /**
     * Mirrored by integrationWireSchema.
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
        // detected == active for core entries; both emitted so a filter-added one can differ.
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
