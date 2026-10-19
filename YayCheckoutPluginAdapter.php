<?php
/**
 * Un-namespaced and excluded from PHP-Scoper so class_exists() checks hit real
 * WordPress/WooCommerce symbols.
 *
 * The edition lives here: dev/edd carries this Pro adapter (licensing on), dev/lite
 * a PluginMenuAdapter. Never merge this file across the two branches.
 */

defined( 'ABSPATH' ) || exit;

class YayCheckoutPluginAdapter implements \YayCheckoutScoped\YayCommerce\AdminShell\License\Contracts\LicenseConfigAdapter {
    // --- Menu methods ---
    public function get_menu_title(): string         { return 'YayCheckout'; }
    public function get_page_title(): string         { return 'YayCheckout Pro - WooCommerce Checkout Builder'; }
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

    /** Optional; AdminShell detects it via method_exists. */
    public function needs_woocommerce_screen(): bool { return ! class_exists( 'WooCommerce' ); }

    // --- License methods ---
    /** The wp_options prefix ({slug}_license_key, {slug}_license_info): changing it loses customers' keys. */
    public function get_plugin_slug(): string        { return 'yaycheckout'; }
    public function get_plugin_name(): string        { return 'YayCheckout Pro - WooCommerce Checkout Builder'; }
    public function get_plugin_version(): string     { return YAYCHECKOUT_VERSION; }
    public function get_plugin_file(): string        { return YAYCHECKOUT_FILE; }
    public function get_item_id(): int               { return 1412; }
    public function get_store_url(): string          { return 'https://yaycommerce.com/'; }
    public function get_store_link(): string         { return YAYCHECKOUT_UPGRADE_URL; }
}
