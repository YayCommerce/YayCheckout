<?php
namespace YayCheckout\Renderer;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Repositories\DesignRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Loads assets/css/checkout-base.css and, after it, the Design tokens with the layout's override. The storefront
 * checkout (CheckoutPage) and the layout editor canvas (LayoutEditorCanvas) both call it,
 * so a layout looks the same in both.
 */
class CheckoutBaseStyles {

    /**
     * @param bool         $with_custom_css Whether the merchant's custom CSS prints after the tokens; false in the editor.
     * @param LayoutDesign $layout_design   The rendered layout's override of the global design.
     */
    public function enqueue( bool $with_custom_css, LayoutDesign $layout_design ): void {
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT_BASE, YAYCHECKOUT_PLUGIN_URL . 'assets/css/checkout-base.css', [], YAYCHECKOUT_VERSION );

        wp_register_style( ScriptName::STYLE_CHECKOUT, false, [ ScriptName::STYLE_CHECKOUT_BASE ], YAYCHECKOUT_VERSION );
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT );
        wp_add_inline_style( ScriptName::STYLE_CHECKOUT, ( new DesignStyles() )->css( ( new DesignRepository() )->get(), $layout_design, $with_custom_css ) );
    }
}
