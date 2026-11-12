<?php
/**
 * Unit test bootstrap. WordPress isn't loaded: stub what the code under test calls with Brain Monkey.
 */

$yay_tests_root = dirname( __DIR__, 3 );
require_once $yay_tests_root . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

spl_autoload_register(
	static function ( $class_name ) use ( $yay_tests_root ) {
		if ( 0 !== strncmp( 'YayCheckout\\', $class_name, 12 ) ) {
			return;
		}
		$file = $yay_tests_root . '/includes/' . str_replace( '\\', '/', substr( $class_name, 12 ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

require_once __DIR__ . '/TestCase.php';
