<?php
/**
 * PHPUnit bootstrap: WordPress test library from composer, plugin loaded as a
 * must-use plugin, components booted without WooCommerce.
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
        require dirname( __DIR__ ) . '/yaycheckout.php';
    }
);

// plugin_init() returns before booting when WooCommerce is absent; layout code
// only needs the manage_woocommerce cap string, so boot the components directly.
tests_add_filter(
    'plugins_loaded',
    static function () {
        if ( ! function_exists( 'WC' ) ) {
            \YayCheckout\YayCheckout::initialize();
        }
    },
    20
);

require $yaycheckout_tests_dir . '/includes/bootstrap.php';
