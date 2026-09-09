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
        $this->boot_premium();

        \YayCheckout\Engine\RestAPI::get_instance();
        \YayCheckout\Engine\Register\RegisterFacade::get_instance();
        \YayCheckout\Engine\Admin\Settings::get_instance();
        \YayCheckout\Blocks\BlockRegistry::get_instance();
        \YayCheckout\PostTypes\LayoutPostType::get_instance();
        \YayCheckout\Engine\LayoutCapGuard::get_instance();
    }

    public static function initialize() {
        self::get_instance();
    }

    /**
     * Load the premium service provider when this build ships one.
     *
     * The only place in core that names a YayCheckout\Premium class. Everything
     * premium attaches to core through hooks from there, so deleting
     * includes/Premium/ produces a working Free build rather than a fatal.
     * scripts/check-premium-boundary.sh enforces that this stays true.
     *
     * Runs before every other component so Plan::is_pro() is settled by the
     * time anything reads it.
     */
    private function boot_premium(): void {
        $provider = YAYCHECKOUT_PLUGIN_DIR . 'includes/Premium/PremiumServiceProvider.php';

        if ( ! is_readable( $provider ) ) {
            return;
        }

        require_once $provider;

        ( new \YayCheckout\Premium\PremiumServiceProvider() )->boot();
    }
}
