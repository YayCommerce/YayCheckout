<?php
/**
 * Un-namespaced and excluded from PHP-Scoper so class_exists() checks hit real
 * WordPress/WooCommerce symbols.
 *
 * The Free edition's adapter: no licensing and no Go Pro link. A build input, not a
 * loaded file — `cmd_release` in scripts/run.sh stages it over the root
 * YayCheckoutPluginAdapter.php (the Pro one, which the branch runs) when --plan=free,
 * so only one of the two ever reaches a zip. Keep the menu methods identical to the
 * root file's; only the licensing half, the page title and the WooCommerce screen differ.
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
    public function get_pro_url(): string            { return ''; }
}
