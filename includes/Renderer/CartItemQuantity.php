<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * The Items block's quantity stepper: a wc-ajax action that sets or removes a cart item.
 *
 * WooCommerce's checkout has no endpoint that sets a cart item's quantity, and its cart form
 * posts the whole cart. This applies one change with WooCommerce's own cart rules; the script
 * then fires update_checkout, so every fragment refreshes through the usual path.
 */
class CartItemQuantity {

    use SingletonTrait;

    public const AJAX_ACTION = 'yaycheckout_cart_item_quantity';

    // WooCommerce ties a guest's nonce to their session only for actions starting with "woocommerce".
    public const NONCE_ACTION = 'woocommerce-yaycheckout-cart-item-quantity';

    protected function __construct() {
        add_action( 'wc_ajax_' . self::AJAX_ACTION, [ $this, 'handle' ] );
    }

    public function handle(): void {
        check_ajax_referer( self::NONCE_ACTION, 'security' );

        if ( null === CheckoutPage::get_instance()->layout() || ! WC()->cart instanceof \WC_Cart ) {
            wp_send_json_error( [ 'message' => self::retry_message() ] );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer() above.
        $cart_item_key = isset( $_POST['cart_item_key'] ) ? (string) wc_clean( wp_unslash( $_POST['cart_item_key'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_clean() runs sanitize_text_field().
        $quantity      = isset( $_POST['quantity'] ) ? (int) wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : -1; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_stock_amount() casts it to a number.
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $error = self::apply( WC()->cart, $cart_item_key, $quantity );

        if ( null !== $error ) {
            wp_send_json_error( [ 'message' => $error ] );
        }

        $empty = WC()->cart->is_empty();

        wp_send_json_success(
            [
                'cart_empty' => $empty,
                'redirect'   => $empty ? wc_get_cart_url() : '',
            ]
        );
    }

    /**
     * Sets a cart item's quantity, or removes it at 0, under WooCommerce's cart rules.
     *
     * @return string|null Null when applied, else a message for the shopper.
     */
    public static function apply( \WC_Cart $cart, string $cart_item_key, int $quantity ): ?string {
        $values = $cart->get_cart_item( $cart_item_key );

        if ( empty( $values ) ) {
            return __( 'This item is no longer in your cart.', 'yaycheckout' );
        }

        if ( $quantity < 0 ) {
            return self::invalid_message();
        }

        if ( 0 === $quantity ) {
            $cart->remove_cart_item( $cart_item_key );
            // remove_cart_item() alone leaves the session holding the item.
            $cart->calculate_totals();

            return null;
        }

        if ( (int) $values['quantity'] === $quantity ) {
            return null;
        }

        $product = $values['data'];

        if ( ! $product instanceof \WC_Product ) {
            return self::invalid_message();
        }

        if ( $product->is_sold_individually() && $quantity > 1 ) {
            /* translators: %s: product name */
            return sprintf( __( 'You can only have 1 %s in your cart.', 'yaycheckout' ), $product->get_name() );
        }

        $max = $product->get_max_purchase_quantity();

        if ( $max > 0 && $quantity > $max ) {
            /* translators: 1: maximum quantity, 2: product name */
            return sprintf( __( 'Only %1$s of %2$s can be bought.', 'yaycheckout' ), $max, $product->get_name() );
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart form filter, so extensions that veto a change there veto it here.
        if ( ! apply_filters( 'woocommerce_update_cart_validation', true, $cart_item_key, $values, $quantity ) ) {
            return self::take_error_notices() ?? self::invalid_message();
        }

        // Refreshes totals, which is what writes the session.
        $cart->set_quantity( $cart_item_key, $quantity );

        return null;
    }

    /** The message for a change that can't be applied, when there is nothing more specific to say. */
    private static function invalid_message(): string {
        return __( 'The quantity could not be updated.', 'yaycheckout' );
    }

    /** The message for a request that failed outright, which the shopper can simply retry. */
    public static function retry_message(): string {
        return __( 'The quantity could not be updated. Please try again.', 'yaycheckout' );
    }

    /** The error notices a validation filter added, as one line of text, cleared so they don't show on the next page. */
    private static function take_error_notices(): ?string {
        $messages = array_filter(
            array_map(
                static function ( $notice ) {
                    return trim( wp_strip_all_tags( is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice ) );
                },
                wc_get_notices( 'error' )
            )
        );

        wc_clear_notices();

        return empty( $messages ) ? null : implode( ' ', $messages );
    }
}
