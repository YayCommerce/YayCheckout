<?php
namespace YayCheckout;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * YayCheckout Plugin Initializer
 */
class YayCheckout {

    use SingletonTrait;

    protected function __construct() {
        $this->boot_pro();

        \YayCheckout\Engine\RestAPI::get_instance();
        \YayCheckout\Engine\Register\RegisterFacade::get_instance();
        \YayCheckout\Engine\Admin\Settings::get_instance();
        \YayCheckout\Blocks\BlockRegistry::get_instance();
        \YayCheckout\PostTypes\LayoutPostType::get_instance();
        \YayCheckout\Engine\LayoutCapGuard::get_instance();
        \YayCheckout\Engine\LayoutScheduleSync::get_instance();
        \YayCheckout\Engine\Admin\LayoutEditorEmbedStyles::get_instance();
    }

    public static function initialize() {
        self::get_instance();
    }

    /**
     * Load the pro service provider when this build ships one.
     *
     * The only place in core that names a YayCheckout\Pro class. Everything
     * Pro attaches to core through hooks from there, so deleting
     * includes/Pro/ produces a working Free build rather than a fatal.
     * scripts/check-pro-boundary.sh enforces that this stays true.
     *
     * Runs before every other component so Plan::is_pro() is settled by the
     * time anything reads it.
     */
    private function boot_pro(): void {
        $provider = YAYCHECKOUT_PLUGIN_DIR . 'includes/Pro/ProServiceProvider.php';

        if ( ! is_readable( $provider ) ) {
            return;
        }

        require_once $provider;

        ( new \YayCheckout\Pro\ProServiceProvider() )->boot();
    }
}
