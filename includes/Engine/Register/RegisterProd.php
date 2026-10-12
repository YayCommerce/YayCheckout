<?php
namespace YayCheckout\Engine\Register;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayCheckout\Utils\SingletonTrait;
use YayCheckout\Engine\Register\ScriptName;

/** Loads the admin app from the built assets. */
class RegisterProd {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ] );
    }

    public function register_all_scripts() {
        wp_register_script( ScriptName::PAGE_SETTINGS, YAYCHECKOUT_PLUGIN_URL . 'assets/dist/admin/js/main.js', ScriptName::PAGE_SETTINGS_DEPS, YAYCHECKOUT_VERSION, true );
        wp_set_script_translations( ScriptName::PAGE_SETTINGS, 'yaycheckout', YAYCHECKOUT_PLUGIN_DIR . 'languages' );
    }
}
