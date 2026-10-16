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

    /** YayBoost's icon, bundled: wordpress.org disallows loading images from a remote host. */
    private const YAYBOOST_ICON_PATH = 'assets/images/integrations/yayboost.png';

    /**
     * Wire shape consumed by the SPA; mirrored by integrationWireSchema.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_integrations(): array {
        $list = [
            self::describe(
                'yayboost',
                __( 'YayBoost', 'yaycheckout' ),
                __( 'Boost conversions, increase AOV, and create urgency with powerful sales-boosting tools for WooCommerce.', 'yaycheckout' ),
                'YAYBOOST_VERSION',
                YAYCHECKOUT_PLUGIN_URL . self::YAYBOOST_ICON_PATH,
                'https://docs.yaycommerce.com/yayboost'
            ),
            self::describe(
                'yayloyalty',
                __( 'YayLoyalty', 'yaycheckout' ),
                __( 'Points balance and redemption at checkout.', 'yaycheckout' ),
                'YAYLOYALTY_VERSION',
                // YayLoyalty has no icon of its own yet; it borrows YayBoost's.
                YAYCHECKOUT_PLUGIN_URL . self::YAYBOOST_ICON_PATH
            ),
        ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- FILTER is a yaycheckout_ prefixed constant.
        return (array) apply_filters( self::FILTER, $list );
    }

    /**
     * One entry of the wire shape.
     *
     * @param string|null $icon_url       Null shows the plugin's initials instead.
     * @param string|null $learn_more_url The plugin's own YayCommerce docs; without them, YayCheckout's.
     */
    private static function describe( string $slug, string $name, string $description, string $version_const, ?string $icon_url = null, ?string $learn_more_url = null ): array {
        // detected == active for core entries; both emitted so a filter-added one can differ.
        $detected = defined( $version_const );

        return [
            'slug'           => $slug,
            'name'           => $name,
            'description'    => $description,
            'detected'       => $detected,
            'version'        => $detected ? (string) constant( $version_const ) : null,
            'active'         => $detected,
            'icon_url'       => $icon_url,
            'learn_more_url' => $learn_more_url ?? YAYCHECKOUT_DOCS_URL,
        ];
    }
}
