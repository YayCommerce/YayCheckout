<?php
/**
 * PHPUnit bootstrap: WordPress test library from composer, WooCommerce and the
 * plugin loaded as must-use plugins so plugin_init() boots through its real path.
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

$yaycheckout_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

// wp-phpunit reads its config path from this variable, not core's WP_TESTS_CONFIG_FILE_PATH.
if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
    putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

require $yaycheckout_tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function () {
        require dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php';
        require dirname( __DIR__ ) . '/yaycheckout.php';
    }
);

// The test library recreates the database on every run, so WooCommerce's tables,
// pages and roles must be installed again; wp_roles() is rebuilt because it was
// cached before WC_Install added shop_manager (core #28374).
tests_add_filter(
    'setup_theme',
    static function () {
        \WC_Install::install();
        $GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        wp_roles();
    }
);

require $yaycheckout_tests_dir . '/includes/bootstrap.php';
