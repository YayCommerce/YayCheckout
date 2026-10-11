<?php
namespace YayCheckout\Engine\Register;

use YayCheckout\Utils\SingletonTrait;
use YayCheckout\Engine\Register\ScriptName;

/** Loads the admin app from the Vite dev server. Stripped from release builds. */
class RegisterDev {
    use SingletonTrait;

    /** Must match DEV_PORT in apps/admin/vite.config.ts. */
    public const DEV_SERVER = 'http://localhost:3040';

    protected function __construct() {
        add_action( 'admin_footer', [ $this, 'render_dev_refresh' ], 5 );

        add_action( 'init', [ $this, 'register_all_scripts' ] );
    }

    public function render_dev_refresh() {
        echo '<script type="module">
        import RefreshRuntime from "' . esc_url( self::DEV_SERVER ) . '/@react-refresh"
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
        </script>';
    }

    public function register_all_scripts() {
        // Version must be null: Vite parses a `?ver=` query as loader options and rejects it.
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_register_script( ScriptName::PAGE_SETTINGS, self::DEV_SERVER . '/main.tsx', ScriptName::PAGE_SETTINGS_DEPS, null, true );
        wp_set_script_translations( ScriptName::PAGE_SETTINGS, 'yaycheckout', YAYCHECKOUT_PLUGIN_DIR . 'languages' );
    }
}
