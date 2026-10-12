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
        \YayCheckout\Engine\DesignFontInstall::get_instance();
        \YayCheckout\Engine\LayoutActiveGuard::get_instance();
        \YayCheckout\Engine\Admin\LayoutEditorEmbed::get_instance();
        \YayCheckout\Engine\Admin\LayoutEditorCanvas::get_instance();
        \YayCheckout\Renderer\PreviewRequest::get_instance();
        \YayCheckout\Renderer\CheckoutScripts::get_instance();
        \YayCheckout\Renderer\CheckoutPage::get_instance();
        \YayCheckout\Renderer\CheckoutAdminBar::get_instance();
        \YayCheckout\Renderer\CheckoutFragments::get_instance();
        \YayCheckout\Renderer\CartItemQuantity::get_instance();
        \YayCheckout\Renderer\HideWhenEmpty::get_instance();
        \YayCheckout\Renderer\PinToBottom::get_instance();
        \YayCheckout\Renderer\LiveValues::get_instance();
        \YayCheckout\Renderer\CheckoutExtraFields::get_instance();
        \YayCheckout\Renderer\CheckoutRequiredFields::get_instance();
        \YayCheckout\Renderer\CheckoutLogin::get_instance();
        \YayCheckout\Renderer\CheckoutDifferentAddress::get_instance();
    }

    public static function initialize() {
        self::get_instance();
    }

    /**
     * Boots the Pro service provider when this build ships one. The only place core
     * names a YayCheckout\Pro class. Runs first so Plan::is_pro() is settled.
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
