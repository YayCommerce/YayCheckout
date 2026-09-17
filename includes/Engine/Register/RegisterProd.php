<?php
namespace YayCheckout\Engine\Register;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayCheckout\Utils\SingletonTrait;
use YayCheckout\Engine\Register\ScriptName;

class RegisterProd {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ] );
    }

    public function register_all_scripts() {
        $deps = [ 'react', 'react-dom', 'wp-element', 'wp-components', 'wp-hooks', 'wp-i18n', 'wp-date' ];
        wp_register_script( ScriptName::PAGE_SETTINGS, YAYCHECKOUT_PLUGIN_URL . 'assets/dist/admin/js/main.js', $deps, YAYCHECKOUT_VERSION, true );
        wp_set_script_translations( ScriptName::PAGE_SETTINGS, 'yaycheckout', YAYCHECKOUT_PLUGIN_DIR . 'languages' );
    }
}
