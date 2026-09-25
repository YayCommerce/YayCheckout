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
            // Code => name, locale-sorted: the list Woo's own country selects show. Some names
            // arrive as HTML entities (S&atilde;o Tom&eacute;); React prints those literally.
            'countries'    => array_map(
                static function ( $name ) {
                    return html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
                },
                WC()->countries->get_countries()
            ),
            // Slug => translated name; every registered role, Woo's and other plugins' included.
            'roles'        => array_map( 'translate_user_role', wp_roles()->get_names() ),
            // Id => the name Woo's Payments settings list shows. Disabled ones too: a rule may be set up before the gateway is switched on.
            'gateways'     => array_map(
                static function ( $gateway ) {
                    return $gateway->get_method_title();
                },
                WC()->payment_gateways()->payment_gateways()
            ),
        ];

        return apply_filters( 'yaycheckout_localize_admin_data', $admin_data );
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
