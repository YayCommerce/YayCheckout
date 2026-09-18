<?php
namespace YayCheckout;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/** Plugin entry point: boots Pro, then every component. */
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
        \YayCheckout\Engine\LayoutTrashSync::get_instance();
        \YayCheckout\Engine\LayoutActiveGuard::get_instance();
        \YayCheckout\Engine\Admin\LayoutEditorEmbed::get_instance();
        \YayCheckout\Renderer\CheckoutScripts::get_instance();
        \YayCheckout\Renderer\CheckoutPage::get_instance();
        \YayCheckout\Checkout\ExtraFields::get_instance();
    }

    public static function initialize() {
        self::get_instance();
    }

    /**
     * Loads the Pro service provider when this build ships one.
     * The only place core may name a YayCheckout\Pro class
     * (scripts/check-pro-boundary.sh). Must run before every other component
     * so Plan::is_pro() is settled.
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
