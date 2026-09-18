<?php
/**
 * Un-namespaced and excluded from PHP-Scoper so class_exists() checks hit real
 * WordPress/WooCommerce symbols.
 */

defined( 'ABSPATH' ) || exit;

class YayCheckoutPluginAdapter implements \YayCheckoutScoped\YayCommerce\AdminShell\Contracts\PluginMenuAdapter {
    public function get_menu_title(): string         { return 'YayCheckout'; }
    public function get_page_title(): string         { return 'YayCheckout - WooCommerce Checkout Builder'; }
    public function get_menu_slug(): string          { return 'yaycheckout'; }
    public function get_settings_page_callback(): ?callable {
        return function () {
            echo '<div id="yaycheckout"></div>';
        };
    }
    public function get_settings_page_position(): ?int { return YAYCHECKOUT_MENU_ORDER; }
    public function get_capability(): string         { return 'manage_woocommerce'; }
    public function get_plugin_basename(): string    { return YAYCHECKOUT_BASE_NAME; }
    public function get_settings_label(): string     { return 'Settings'; }
    public function get_docs_url(): string           { return 'https://docs.yaycommerce.com/'; }
    public function get_pro_url(): string            { return 'https://yaycommerce.com/'; }

    /** Optional; AdminShell detects it via method_exists. */
    public function needs_woocommerce_screen(): bool { return ! class_exists( 'WooCommerce' ); }
}
