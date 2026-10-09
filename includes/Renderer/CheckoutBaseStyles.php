<?php
namespace YayCheckout\Renderer;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\DesignRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Loads assets/css/checkout-base.css and, after it, the Design tokens. The storefront
 * checkout (CheckoutPage) and the layout editor canvas (LayoutEditorCanvas) both call it,
 * so a layout looks the same in both.
 */
class CheckoutBaseStyles {

    /**
     * @param bool        $with_custom_css Whether the merchant's custom CSS prints after the tokens; false in the editor.
     * @param Layout|null $layout          The layout shown; its design layer applies on top of global (Pro).
     */
    public function enqueue( bool $with_custom_css, ?Layout $layout = null ): void {
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT_BASE, YAYCHECKOUT_PLUGIN_URL . 'assets/css/checkout-base.css', [], YAYCHECKOUT_VERSION );

        wp_register_style( ScriptName::STYLE_CHECKOUT, false, [ ScriptName::STYLE_CHECKOUT_BASE ], YAYCHECKOUT_VERSION );
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT );
        wp_add_inline_style( ScriptName::STYLE_CHECKOUT, ( new DesignStyles() )->css( ( new DesignRepository() )->for_layout( $layout ), $with_custom_css ) );
    }
}
