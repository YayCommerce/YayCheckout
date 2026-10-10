<?php
namespace YayCheckout\Renderer;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Repositories\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Loads assets/css/checkout-base.css and, after it, the layout's design tokens. The storefront
 * checkout (CheckoutPage) and the layout editor canvas (LayoutEditorCanvas) both call it,
 * so a layout looks the same in both.
 */
class CheckoutBaseStyles {

    /**
     * @param bool         $with_custom_css Whether the site-wide and the layout's custom CSS print after the tokens; false in the editor.
     * @param LayoutDesign $design          The rendered layout's design.
     */
    public function enqueue( bool $with_custom_css, LayoutDesign $design ): void {
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT_BASE, YAYCHECKOUT_PLUGIN_URL . 'assets/css/checkout-base.css', [], YAYCHECKOUT_VERSION );

        wp_register_style( ScriptName::STYLE_CHECKOUT, false, [ ScriptName::STYLE_CHECKOUT_BASE ], YAYCHECKOUT_VERSION );
        wp_enqueue_style( ScriptName::STYLE_CHECKOUT );
        $site_css = $with_custom_css ? ( new SettingsRepository() )->get()->advanced['custom_css'] : '';

        wp_add_inline_style( ScriptName::STYLE_CHECKOUT, ( new DesignStyles() )->css( $design, $site_css, $with_custom_css ) );
    }
}
