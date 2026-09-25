<?php
namespace YayCheckout\Helpers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Repositories\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Localized data for the admin React app.
 * VAR_ADMIN → window.yayCheckoutAdmin, VAR_META → window.yayCheckoutMeta.
 */
class LocalizeHelper {
    public const VAR_ADMIN = 'yayCheckoutAdmin';
    public const VAR_META  = 'yayCheckoutMeta';

    public static function get_admin_data() {
        $admin_data = [
            'settings'     => ( new SettingsRepository() )->get()->to_array(),
            'design'       => ( new DesignRepository() )->get()->to_array(),
            'integrations' => IntegrationsHelper::get_integrations(),
            'store'        => self::get_store_data(),
        ];

        return apply_filters( 'yaycheckout_localize_admin_data', $admin_data );
    }

    /**
     * Store lists and formats the SPA's pickers need: what Woo and WP already hold, so the SPA never re-declares them.
     */
    private static function get_store_data(): array {
        return [
            // Code => name, locale-sorted: the list Woo's own country selects show. Some names
            // arrive as HTML entities (S&atilde;o Tom&eacute;); React prints those literally.
            'countries' => array_map(
                static function ( $name ) {
                    return html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
                },
                WC()->countries->get_countries()
            ),
            // Slug => translated name; every registered role, Woo's and other plugins' included.
            'roles'     => array_map( 'translate_user_role', wp_roles()->get_names() ),
            // Id => the name Woo's Payments settings list shows. Disabled ones too: a rule may be set up before the gateway is switched on.
            'gateways'  => array_map(
                static function ( $gateway ) {
                    return $gateway->get_method_title();
                },
                WC()->payment_gateways()->payment_gateways()
            ),
            // Store currency format, so amounts are typed the way Woo prints prices.
            'currency'  => [
                'symbol'             => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
                'position'           => get_option( 'woocommerce_currency_pos', 'left' ),
                'decimals'           => wc_get_price_decimals(),
                'decimal_separator'  => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
            ],
        ];
    }

    public static function get_meta() {
        $meta = [
            'wpMeta'       => [
                'siteUrl'      => esc_url_raw( site_url() ),
                'adminUrl'     => esc_url_raw( admin_url() ),
                'ajaxUrl'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),

                'restRoot'     => esc_url_raw( rest_url() ),
                'restBase'     => BaseRestController::REST_NAMESPACE,
                'restNonce'    => wp_create_nonce( 'wp_rest' ),
                'isBlockTheme' => wp_is_block_theme(),
            ],
            'checkoutMeta' => [
                'pluginUrl'  => YAYCHECKOUT_PLUGIN_URL,
                'assetsUrl'  => YAYCHECKOUT_PLUGIN_URL . 'assets',
                'version'    => YAYCHECKOUT_VERSION,
                'docsUrl'    => YAYCHECKOUT_DOCS_URL,
                'upgradeUrl' => YAYCHECKOUT_UPGRADE_URL,
                'reviewed'   => ( new SettingsRepository() )->is_reviewed(),
            ],
        ];

        return apply_filters( 'yaycheckout_localize_meta', $meta );
    }
}
