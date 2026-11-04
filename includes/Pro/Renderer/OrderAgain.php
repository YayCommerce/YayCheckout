<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Pro\Repositories\PreviewOrderRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: the Order Again Button's form handler. Puts a past order's items back in the shopper's cart,
 * without its coupons, then opens the checkout.
 *
 * WooCommerce's own order again (WC_Cart_Session::populate_cart_from_order()) is private, takes
 * logged-in shoppers and completed orders only, and lands on the cart. This runs the same filters
 * per item, so add-ons keep their options and quantity limits still apply, and adds each item
 * through WC_Cart::add_to_cart(), which refuses what is out of stock or can't be bought.
 *
 * Fields are not prefilled here: the WooCommerce session still holds the customer's addresses and
 * payment method after the order, so the checkout prints them again.
 */
class OrderAgain {

    use SingletonTrait;

    /** POST field holding the order id. */
    public const ACTION = 'yaycheckout_order_again';

    /** POST field holding the order key, a guest's proof that the order is theirs. */
    public const KEY_FIELD = 'order_key';

    // WooCommerce ties a guest's nonce to their session only for actions starting with "woocommerce".
    public const NONCE = 'woocommerce-yaycheckout-order-again';

    /** After WC_Form_Handler's cart actions on wp_loaded, once the cart is loaded from the session. */
    private const PRIORITY = 20;

    protected function __construct() {
        add_action( 'wp_loaded', [ $this, 'maybe_handle' ], self::PRIORITY );
    }

    /**
     * Whether the shopper may order this order again. An order with an account takes that account
     * signed in, whatever key is given, so a leaked thank-you link can't refill a stranger's cart
     * from it. A guest order takes its key: every guest has user id 0, so WooCommerce's order_again
     * capability would let any guest through.
     */
    public static function can_reorder( \WC_Order $order, string $key ): bool {
        /**
         * Order statuses the Order Again Button shows for and accepts.
         *
         * @param string[] $statuses Status slugs without the wc- prefix.
         */
        $statuses = apply_filters( 'yaycheckout_order_again_statuses', [ 'processing', 'on-hold', 'completed' ] );

        if ( ! $order->has_status( $statuses ) || '' !== (string) $order->get_meta( PreviewOrderRepository::META_FLAG ) ) {
            return false;
        }

        $owner = $order->get_user_id();

        if ( $owner > 0 ) {
            return get_current_user_id() === $owner;
        }

        return '' !== $key && hash_equals( $order->get_order_key(), $key );
    }

    /**
     * Empties the cart and its coupons, then adds the order's items back. Items that are gone, out of
     * stock, refused by a validation filter or by the cart's own rules are skipped; WooCommerce prints
     * a notice for the ones its cart refuses, and this adds one counting them all.
     *
     * @return int The number of items added.
     */
    public function refill( \WC_Order $order ): int {
        $cart = WC()->cart;

        $cart->empty_cart();
        $cart->remove_coupons();

        $items = $order->get_items();
        $added = 0;

        foreach ( $items as $item ) {
            if ( $item instanceof \WC_Order_Item_Product && $this->add_item( $order, $item ) ) {
                ++$added;
            }
        }

        // By reference, as WooCommerce passes it: an extension may change the refilled cart.
        $contents = $cart->get_cart_contents();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own order-again action, for extensions that follow it.
        do_action_ref_array( 'woocommerce_ordered_again', [ $order->get_id(), $items, &$contents ] );

        if ( $contents !== $cart->get_cart_contents() ) {
            $cart->set_cart_contents( $contents );
            // Saves the changed cart to the session.
            $cart->calculate_totals();
        }

        $missing = count( $items ) - $added;

        if ( $missing > 0 ) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: number of items. */
                    _n(
                        '%d item from your previous order is currently unavailable and could not be added to your cart.',
                        '%d items from your previous order are currently unavailable and could not be added to your cart.',
                        $missing,
                        'yaycheckout'
                    ),
                    $missing
                ),
                'error'
            );
        }

        return $added;
    }

    /** Refills the cart from a posted Order Again Button, then opens the checkout, or the cart when nothing was added. */
    public function maybe_handle(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified below, before anything changes.
        if ( ! isset( $_POST[ self::ACTION ] ) || ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
            return;
        }

        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
            return;
        }

        $order = wc_get_order( absint( $_POST[ self::ACTION ] ) );
        $key   = isset( $_POST[ self::KEY_FIELD ] ) ? (string) wc_clean( wp_unslash( $_POST[ self::KEY_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_clean() runs sanitize_text_field().
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( ! $order instanceof \WC_Order || ! self::can_reorder( $order, $key ) ) {
            return;
        }

        wc_nocache_headers();

        $added = $this->refill( $order );

        wp_safe_redirect( $added > 0 ? wc_get_checkout_url() : wc_get_cart_url() );
        exit;
    }

    /** Adds one order line, as WC_Cart_Session::populate_cart_from_order() rebuilds it. */
    private function add_item( \WC_Order $order, \WC_Order_Item_Product $item ): bool {
        $product = $item->get_product();

        if ( ! $product instanceof \WC_Product ) {
            return false;
        }

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's add-to-cart and order-again filters.
        $product_id   = (int) apply_filters( 'woocommerce_add_to_cart_product_id', $item->get_product_id() );
        $variation_id = (int) $item->get_variation_id();
        $quantity     = (int) $item->get_quantity();

        // A variable product without its variation can't be added; WooCommerce skips it too.
        if ( 0 === $variation_id && $product->is_type( 'variable' ) ) {
            return false;
        }

        $variations = self::variations( $item, $product_id );
        $data       = (array) apply_filters( 'woocommerce_order_again_cart_item_data', [], $item, $order );

        if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations, $data ) ) {
            return false;
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        return false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $data );
    }

    /**
     * The item's attribute choices, keyed attribute_<slug>, read from its meta as WooCommerce does.
     *
     * @return array<string, string>
     */
    private static function variations( \WC_Order_Item_Product $item, int $product_id ): array {
        $variations = [];

        foreach ( $item->get_meta_data() as $meta ) {
            $taxonomy = taxonomy_is_product_attribute( $meta->key );

            if ( ! $taxonomy && ! meta_is_product_attribute( $meta->key, $meta->value, $product_id ) ) {
                continue;
            }

            $variations[ 'attribute_' . sanitize_title( $meta->key ) ] = $taxonomy
                ? sanitize_title( $meta->value )
                : html_entity_decode( wc_clean( $meta->value ), ENT_QUOTES, get_bloginfo( 'charset' ) );
        }

        return $variations;
    }
}
