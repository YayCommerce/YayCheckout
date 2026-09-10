<?php
namespace YayCheckout\Helpers;

use YayCheckout\Controllers\BaseRestController;

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
            'settings' => SettingsHelper::get_settings(),
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
                'pluginUrl' => YAYCHECKOUT_PLUGIN_URL,
                'assetsUrl' => YAYCHECKOUT_PLUGIN_URL . 'assets',
                'version'   => YAYCHECKOUT_VERSION,
                'reviewed'  => (bool) get_option( SettingsHelper::OPTION_REVIEWED, false ),
            ],
        ];

        return apply_filters( 'yaycheckout_localize_meta', $meta );
    }
}
