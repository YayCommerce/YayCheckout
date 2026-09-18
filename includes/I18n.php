<?php

namespace YayCheckout;

defined( 'ABSPATH' ) || exit;
class I18n {

    public static function load_plugin_textdomain() {
        if ( function_exists( 'determine_locale' ) ) {
            $locale = determine_locale();
        } else {
            $locale = is_admin() ? get_user_locale() : get_locale();
        }
        unload_textdomain( 'yaycheckout' );
        load_textdomain( 'yaycheckout', YAYCHECKOUT_PLUGIN_DIR . '/languages/yaycheckout-' . $locale . '.mo' );
        load_plugin_textdomain( 'yaycheckout', false, YAYCHECKOUT_PLUGIN_DIR . '/languages/' );
    }
}
