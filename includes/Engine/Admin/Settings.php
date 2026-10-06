<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Utils\SingletonTrait;
use YayCheckout\Helpers\LocalizeHelper;
use YayCheckout\Engine\Register\ScriptName;

defined( 'ABSPATH' ) || exit;
/** Enqueues the SPA bundle and its localized data on the YayCheckout screen. */
class Settings {
    use SingletonTrait;

    protected function __construct() {

        add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );

        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
    }

    public function admin_body_class( string $classes ): string {
        if ( strpos( $classes, 'yay-ui' ) === false ) {
            $classes .= ' yay-ui';
        }
        return $classes;
    }

    public function admin_enqueue_scripts( $hook_suffix ) {

        // AdminShell submenu: yaycommerce (parent) + yaycheckout (slug).
        $allow_hook_suffixes = [ 'yaycommerce_page_yaycheckout' ];

        if ( ! in_array( $hook_suffix, $allow_hook_suffixes, true ) ) {
            return;
        }

        wp_localize_script(
            ScriptName::PAGE_SETTINGS,
            LocalizeHelper::VAR_ADMIN,
            LocalizeHelper::get_admin_data(),
        );

        wp_localize_script(
            ScriptName::PAGE_SETTINGS,
            LocalizeHelper::VAR_META,
            LocalizeHelper::get_meta(),
        );

        wp_enqueue_script( ScriptName::PAGE_SETTINGS );
        wp_enqueue_style( ScriptName::STYLE_SETTINGS );
    }
}
