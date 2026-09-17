<?php
/**
 * Plugin Name:       YayCheckout - WooCommerce Checkout Builder
 * Plugin URI:        https://yaycommerce.com/
 * Description:       Checkout builder plugin for WooCommerce: customize checkout fields, layout and design.
 * Version:           1.0.0
 * Author:            YayCommerce
 * Author URI:        https://yaycommerce.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yaycheckout
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 10.0.0
 * WC tested up to: 10.7.0
 *
 * @package yaycommerce/yaycheckout
 */

namespace YayCheckout;

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'YayCheckout\\plugin_init' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/Fallback.php';
    add_action(
        'admin_init',
        function () {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
    );
}

if ( ! defined( 'YAYCHECKOUT_FILE' ) ) {
    define( 'YAYCHECKOUT_FILE', __FILE__ );
}

if ( ! defined( 'YAYCHECKOUT_VERSION' ) ) {
    define( 'YAYCHECKOUT_VERSION', '1.0.0' );
}

if ( ! defined( 'YAYCHECKOUT_PLUGIN_URL' ) ) {
    define( 'YAYCHECKOUT_PLUGIN_URL', plugin_dir_url( YAYCHECKOUT_FILE ) );
}

if ( ! defined( 'YAYCHECKOUT_PLUGIN_DIR' ) ) {
    define( 'YAYCHECKOUT_PLUGIN_DIR', plugin_dir_path( YAYCHECKOUT_FILE ) );
}

if ( ! defined( 'YAYCHECKOUT_BASE_NAME' ) ) {
    define( 'YAYCHECKOUT_BASE_NAME', plugin_basename( YAYCHECKOUT_FILE ) );
}

if ( ! defined( 'YAYCHECKOUT_MENU_ORDER' ) ) {
    define( 'YAYCHECKOUT_MENU_ORDER', 4 );
}

if ( ! defined( 'YAYCHECKOUT_DOCS_URL' ) ) {
    define( 'YAYCHECKOUT_DOCS_URL', 'http://docs.yaycommerce.com/yaycheckout' );
}

// Off unless wp-config.php says otherwise: the release ZIP ships no RegisterDev.
if ( ! defined( 'YAYCHECKOUT_IS_DEVELOPMENT' ) ) {
    define( 'YAYCHECKOUT_IS_DEVELOPMENT', false );
}

// Plugin classes: YayCheckout\* → includes/*.
spl_autoload_register(
    function ( $class_name ) {
        $prefix   = __NAMESPACE__;
        $base_dir = __DIR__ . '/includes';

        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
            return;
        }

        $relative_class_name = substr( $class_name, $len );

        $file = $base_dir . str_replace( '\\', '/', $relative_class_name ) . '.php';

        if ( file_exists( $file ) ) {
            /*
             * require_once, not require: a malformed class name — a doubled
             * backslash from another plugin's class_exists(), say — maps to the
             * same file through a different string, and a plain require would
             * then redeclare the class and fatal the whole site.
             */
            require_once $file;
        }
    }
);

// Scoped admin shell + root adapter (composer classmap: vendor-prefixed/src + adapter).
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/YayCheckoutPluginAdapter.php';

// Boot the shared YayCommerce admin shell early so version election across
// coexisting Yay plugins happens before menus register.
add_action(
    'plugins_loaded',
    function () {
        \YayCheckoutScoped\YayCommerce\AdminShell\AdminShell::boot();
        \YayCheckoutScoped\YayCommerce\AdminShell\AdminShell::register_plugin(
            new \YayCheckoutPluginAdapter()
        );
    },
    5
);

if ( ! function_exists( 'YayCheckout\\plugin_init' ) ) {
    function plugin_init() {
        if ( ! function_exists( 'WC' ) ) {
            add_action( 'admin_notices', [ \YayCheckout\Engine\ActDeact::class, 'install_woocommerce_admin_notice' ] );
            return;
        }

        add_action( 'before_woocommerce_init', [ \YayCheckout\Engine\ActDeact::class, 'before_woocommerce_init' ] );

        YayCheckout::initialize();
        I18n::load_plugin_textdomain();
    }
}//end if

if ( ! wp_installing() ) {
    add_action( 'plugins_loaded', 'YayCheckout\\plugin_init' );
}

register_activation_hook( YAYCHECKOUT_FILE, [ \YayCheckout\Engine\ActDeact::class, 'activate' ] );
register_deactivation_hook( YAYCHECKOUT_FILE, [ \YayCheckout\Engine\ActDeact::class, 'deactivate' ] );
