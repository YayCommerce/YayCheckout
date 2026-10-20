<?php
namespace YayCheckout\Helpers;

use YayCheckout\Integrations\YayBoost;
use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Other Yay plugins' blocks that a layout can hold a placeholder for (yaycheckout/plugin-block), and where each
 * stands on this site. The editor reads editor_data() as window.yayCheckoutPluginBlocks; the placeholder's
 * variations, card and states all come from it.
 */
class PluginBlockCatalogue {

    /** The placeholder block, packages/blocks/src/plugin-block/block.json. */
    public const BLOCK = 'yaycheckout/plugin-block';

    /** Entries follow the shape yayboost_entry() builds, before editor_data() resolves their state. */
    public const FILTER = 'yaycheckout_plugin_blocks';

    private const YAYBOOST_FILE = 'yayboost-sales-booster-for-woocommerce.php';

    private const YAYBOOST_SLUG = 'yayboost-sales-booster-for-woocommerce';

    /** The YayCommerce Licenses screen, where YayBoost Pro takes its key. */
    private const YAYCOMMERCE_LICENSES_PATH = 'admin.php?page=yaycommerce-licenses';

    /**
     * The catalogue after the filter.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function entries(): array {
        $entries = [
            self::yayboost_entry(
                YayBoost::ORDER_BUMP_BLOCK,
                __( 'Order bump', 'yaycheckout' ),
                __( 'Offer a one-click add-on right before payment.', 'yaycheckout' ),
                'admin.php?page=yayboost-settings#/features/order_bump',
                YayBoost::ORDER_BUMP,
                YayBoost::ORDER_BUMP_SHORTCODE
            ),
            self::yayboost_entry(
                YayBoost::FREE_SHIPPING_BAR_BLOCK,
                __( 'Free shipping bar', 'yaycheckout' ),
                __( 'Show how much more the shopper needs to spend for free shipping.', 'yaycheckout' ),
                'admin.php?page=yayboost-settings#/features/free_shipping_bar',
                YayBoost::FREE_SHIPPING_BAR,
                YayBoost::FREE_SHIPPING_BAR_SHORTCODE
            ),
            self::yayboost_entry(
                YayBoost::FBT_BLOCK,
                __( 'Frequently bought together', 'yaycheckout' ),
                __( 'Suggest products that go with the cart, added in one click.', 'yaycheckout' ),
                'admin.php?page=yayboost-settings#/features/frequently_bought_together',
                YayBoost::FBT,
                YayBoost::FBT_SHORTCODE
            ),
        ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- FILTER is a yaycheckout_ prefixed constant.
        $filtered = array_values( (array) apply_filters( self::FILTER, $entries ) );

        // A filtered-in entry may leave out the optional fields; every reader then gets them.
        return array_map(
            static function ( array $entry ): array {
                return $entry + [
                    'featureId'  => '',
                    'shortcode'  => '',
                    'licenseUrl' => '',
                    'hooks'      => [],
                ];
            },
            $filtered
        );
    }

    /**
     * The targets the catalogue holds spots for.
     *
     * @return string[]
     */
    public static function targets(): array {
        return array_values( array_filter( array_column( self::entries(), 'target' ), 'is_string' ) );
    }

    /**
     * The catalogue with each entry's plugin path and state on this site, and what the user may do about it.
     * Wire shape consumed by packages/blocks/src/plugin-block/plugin-blocks-data.ts.
     */
    public static function editor_data(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed    = array_keys( get_plugins() );
        $by_file      = array_combine( array_map( 'basename', $installed ), $installed );
        $integrations = array_column( IntegrationsHelper::get_integrations(), null, 'slug' );
        $entries      = [];

        foreach ( self::entries() as $entry ) {
            $plugin_path = $by_file[ $entry['pluginFile'] ] ?? null;

            if ( ! $entry['available'] ) {
                $state = 'unavailable';
            } elseif ( null === $plugin_path ) {
                $state = 'not_installed';
            } elseif ( ! is_plugin_active( $plugin_path ) ) {
                $state = 'inactive';
            } elseif ( 'yayboost' === $entry['plugin'] && false === YayBoost::get_instance()->is_licensed() ) {
                // Unlicensed YayBoost Pro boots nothing: no feature, no shortcode.
                $state = 'unlicensed';
            } elseif ( self::feature_enabled( $entry ) && shortcode_exists( $entry['shortcode'] ) ) {
                // Both YayBoost editions fill the spot through the shortcode.
                $state = 'shortcode';
            } else {
                $state = 'feature_off';
            }

            $integration = $integrations[ $entry['plugin'] ] ?? [];
            $entries[]   = array_merge(
                $entry,
                [
                    'pluginName'   => $integration['name'] ?? $entry['plugin'],
                    'learnMoreUrl' => $integration['learn_more_url'] ?? $entry['learnMoreUrl'],
                    'iconUrl'      => $integration['icon_url'] ?? null,
                    'settingsUrl'  => '' === $entry['settingsUrl'] ? '' : admin_url( $entry['settingsUrl'] ),
                    'licenseUrl'   => '' === $entry['licenseUrl'] ? '' : admin_url( $entry['licenseUrl'] ),
                    'installUrl'   => admin_url( 'plugin-install.php?s=' . rawurlencode( $entry['wporgSlug'] ) . '&tab=search&type=term' ),
                    'pluginsUrl'   => admin_url( 'plugins.php' ),
                    'pluginPath'   => $plugin_path,
                    'state'        => $state,
                    // Where the plugin prints the feature in a layout without a spot; the editor shows a stand-in there.
                    'hooks'        => 'yayboost' === $entry['plugin'] && YayBoost::ORDER_BUMP === $entry['featureId'] ? YayBoost::get_instance()->order_bump_hooks() : $entry['hooks'],
                ]
            );
        }

        return [
            'canInstall'  => current_user_can( 'install_plugins' ),
            'canActivate' => current_user_can( 'activate_plugins' ),
            'entries'     => $entries,
        ];
    }

    /**
     * The catalogue targets $content holds a placeholder for, in document order. The plugin's own block for a target
     * is no spot: it prints the feature beside the plugin's hook output, which stays.
     *
     * @return string[]
     */
    public static function spots_in( string $content ): array {
        $targets = self::targets();
        $spots   = array_map(
            static fn( array $block ): string => self::spot_for( $block, $targets ),
            LayoutMarkupReader::flatten( parse_blocks( $content ) )
        );

        return array_values( array_unique( array_filter( $spots ) ) );
    }

    /**
     * The target among $targets that $block, a placeholder, holds a spot for; '' for none.
     *
     * @param array    $block   A parsed block.
     * @param string[] $targets Catalogue targets.
     */
    public static function spot_for( array $block, array $targets ): string {
        if ( self::BLOCK !== ( $block['blockName'] ?? '' ) ) {
            return '';
        }

        $target = (string) ( $block['attrs']['target'] ?? '' );

        return in_array( $target, $targets, true ) ? $target : '';
    }

    /**
     * The markup a template prints for a spot that $target fits: its placeholder.
     *
     * @param array $attributes The placeholder's attributes besides its target.
     */
    public static function block( string $target, array $attributes = [] ): string {
        return get_comment_delimited_block_content( self::BLOCK, array_merge( [ 'target' => $target ], $attributes ), '' );
    }

    /** Whether the entry's plugin reports its feature on; unknown counts as off. */
    private static function feature_enabled( array $entry ): bool {
        return 'yayboost' === $entry['plugin'] && true === YayBoost::get_instance()->feature_enabled( $entry['featureId'] );
    }

    /**
     * One YayBoost entry.
     *
     * @param string $settings_path The plugin's settings screen for the block's feature, relative to wp-admin; '' for none.
     * @param string $feature_id    YayBoost's id for the block's feature, e.g. order_bump; '' for none.
     * @param string $shortcode     The shortcode that prints the feature without the block; '' for none.
     * @param bool   $available     False while the plugin has no such block yet.
     */
    private static function yayboost_entry( string $target, string $title, string $pitch, string $settings_path, string $feature_id, string $shortcode, bool $available = true ): array {
        return [
            'target'       => $target,
            'plugin'       => 'yayboost',
            'pluginFile'   => self::YAYBOOST_FILE,
            'wporgSlug'    => self::YAYBOOST_SLUG,
            'title'        => $title,
            'pitch'        => $pitch,
            'settingsUrl'  => $settings_path,
            'featureId'    => $feature_id,
            'shortcode'    => $shortcode,
            'licenseUrl'   => self::YAYCOMMERCE_LICENSES_PATH,
            // IntegrationsHelper's learn_more_url wins in editor_data(); this is the fallback for a filtered-out integration.
            'learnMoreUrl' => YAYCHECKOUT_DOCS_URL,
            'available'    => $available,
        ];
    }
}
