<?php
namespace YayCheckout\Integrations;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Fixes for Germanized for WooCommerce, active only while that plugin is.
 *
 * Germanized adds a thumbnail to every cart item name. The Items block prints its own, so each
 * row got a second 300px image; the injection is off while the Items block prints its rows.
 */
class Germanized {

    use SingletonTrait;

    /** Germanized greys every `.woocommerce-checkout .shop_table`; YayCheckout's own tables sit on their block's background. */
    private const TABLES_CSS = '.yaycheckout-page table:is(.yaycheckout-order-summary-breakdown, .yaycheckout-order-summary-total, .yaycheckout-order-summary-items__hook, .yaycheckout-shipping-methods__extensions) { background-color: transparent; }';

    /** Germanized's units, description and delivery time are <p>/<div> in the item name: no theme margins, and the look of the SKU line in order-summary-items/style.css. */
    private const ITEM_INFO_CSS = '.yaycheckout-order-summary-items .wc-gzd-cart-info { margin: 0; font-size: 0.875em; opacity: 0.75; }';

    protected function __construct() {
        if ( ! function_exists( 'WC_germanized' ) ) {
            return;
        }

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's review-order hooks, which the Items block fires around its rows.
        add_action( 'woocommerce_review_order_before_cart_contents', [ $this, 'skip_item_thumbnail' ] );
        add_action( 'woocommerce_review_order_after_cart_contents', [ $this, 'restore_item_thumbnail' ] );

        // Germanized prints its checkout checkboxes after the payment methods, or before them when it turns its checkout adjustments off.
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'skip_payment_checkboxes' ], 9 );
        add_action( 'woocommerce_review_order_after_payment', [ $this, 'skip_payment_checkboxes' ], 9 );
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        add_action( 'yaycheckout_checkout_terms', 'woocommerce_gzd_template_render_checkout_checkboxes' );

        // After init, where Germanized adds its submit button; before the layout renders.
        add_action( 'wp', [ $this, 'keep_place_order_button' ] );

        // After CheckoutPage enqueues checkout-base at 20.
        add_action( 'wp_enqueue_scripts', [ $this, 'add_styles' ], 21 );
    }

    /** This plugin's style fixes, printed only where a layout renders the checkout. */
    public function add_styles(): void {
        if ( ! CheckoutPage::get_instance()->takes_over() ) {
            return;
        }

        wp_add_inline_style( ScriptName::STYLE_CHECKOUT_BASE, self::TABLES_CSS );
        wp_add_inline_style( generate_block_asset_handle( 'yaycheckout/order-summary-items', 'style' ), self::ITEM_INFO_CSS );
    }

    /**
     * Germanized hides Woo's button to print its own at woocommerce_checkout_order_review, which
     * a layout never fires, so the Place Order block lost its button. Without Germanized's button
     * Germanized leaves Woo's button and its submit hooks alone.
     */
    public function keep_place_order_button(): void {
        if ( CheckoutPage::get_instance()->takes_over() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's checkout hook.
            remove_action( 'woocommerce_checkout_order_review', 'woocommerce_gzd_template_order_submit', wc_gzd_get_hook_priority( 'checkout_order_submit' ) );
        }
    }

    /**
     * With a Terms block the legal checkboxes print there instead, so the Payment block skips them.
     * Without one they stay beside the payment methods, the only place a shopper can tick them.
     */
    public function skip_payment_checkboxes(): void {
        $layout = CheckoutPage::get_instance()->layout();

        if ( null !== $layout && has_block( 'yaycheckout/terms', $layout->content ) ) {
            remove_action( current_action(), 'woocommerce_gzd_template_render_checkout_checkboxes', 10 );
        }
    }

    public function skip_item_thumbnail(): void {
        if ( null !== CheckoutPage::get_instance()->layout() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Germanized's filter.
            add_filter( 'woocommerce_gzd_checkout_table_needs_thumbnail', '__return_false' );
        }
    }

    public function restore_item_thumbnail(): void {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Germanized's filter.
        remove_filter( 'woocommerce_gzd_checkout_table_needs_thumbnail', '__return_false' );
    }
}
