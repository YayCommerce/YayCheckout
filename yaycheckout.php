<?php
/**
 * Plugin Name:       YayCheckout Pro - WooCommerce Checkout Builder
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
 * WC requires at least: 10.0
 * WC tested up to: 11.2
 */

namespace YayCheckout;

defined( 'ABSPATH' ) || exit;

// Pro supersedes Free. 'yaycheckout/' loads before 'yaycheckout-pro/', so without this the
// guard below would keep Free and switch Pro off. Read the options: is_plugin_active()
// isn't loaded on the storefront.
$yaycheckout_pro_basename = 'yaycheckout-pro/yaycheckout.php';
if ( 'yaycheckout-pro' !== basename( __DIR__ ) && (
    in_array( $yaycheckout_pro_basename, (array) get_option( 'active_plugins', [] ), true )
    || ( is_multisite() && isset( get_site_option( 'active_sitewide_plugins', [] )[ $yaycheckout_pro_basename ] ) )
) ) {
    $yaycheckout_fallback_reason = 'pro_active';
    require_once plugin_dir_path( __FILE__ ) . 'includes/Fallback.php';
    add_action(
        'admin_init',
        function () {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
    );
    return;
}

if ( function_exists( 'YayCheckout\\plugin_init' ) ) {
    $yaycheckout_fallback_reason = 'duplicate';
    require_once plugin_dir_path( __FILE__ ) . 'includes/Fallback.php';
    add_action(
        'admin_init',
        function () {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
    );
    // The other copy already loaded the autoloaders; loading this copy's too is a fatal redeclare.
    return;
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

if ( ! defined( 'YAYCHECKOUT_UPGRADE_URL' ) ) {
    define( 'YAYCHECKOUT_UPGRADE_URL', 'https://yaycommerce.com/yaycheckout/' );
}

if ( ! defined( 'YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL' ) ) {
    define( 'YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL', 'https://yaycommerce.com/yaycheckout/compatibility.json' );
}

if ( ! defined( 'YAYCHECKOUT_IS_DEVELOPMENT' ) ) {
    define( 'YAYCHECKOUT_IS_DEVELOPMENT', true );
}

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
            // require_once: a doubled-backslash class name maps to the same file and would redeclare.
            require_once $file;
        }
    }
);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/YayCheckoutPluginAdapter.php';

// Priority 5: version election across coexisting Yay plugins must precede menu registration.
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
    }
}//end if

if ( ! wp_installing() ) {
    add_action( 'plugins_loaded', 'YayCheckout\\plugin_init' );
}

register_activation_hook( YAYCHECKOUT_FILE, [ \YayCheckout\Engine\ActDeact::class, 'activate' ] );
register_deactivation_hook( YAYCHECKOUT_FILE, [ \YayCheckout\Engine\ActDeact::class, 'deactivate' ] );
