<?php
namespace YayCheckout\Integrations;

use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Fixes for Germanized for WooCommerce, active only while that plugin is.
 *
 * Germanized adds a thumbnail to every cart item name and wraps the name in a div that only its
 * quantity filter closes. The Items block prints its own thumbnail and, with a badge or a stepper,
 * skips that filter, so each row got a second 300px image and a div that never closed, which
 * nested the next row inside this one. Turning the injection off while the Items block prints
 * its rows removes both.
 */
class Germanized {

    use SingletonTrait;

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
