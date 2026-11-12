<?php
namespace YayTests\Unit;

final class PluginHeaderTest extends TestCase {

	public function test_main_file_declares_the_plugin() {
		$header = file_get_contents( dirname( __DIR__, 3 ) . '/yaycheckout.php' );

		$this->assertMatchesRegularExpression( '/Plugin Name:\s*' . preg_quote( 'YayCheckout Pro - WooCommerce Checkout Builder', '/' ) . '/', $header );
	}
}
