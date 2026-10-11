<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Which address is the main one, from WooCommerce's Shipping destination setting, and the copy
 * that makes "Use same address for billing" hold when shipping is the main address: the posted
 * shipping address replaces billing's, and billing's address fields are optional for that request.
 *
 * Stands down when CheckoutPage has no layout, so stock WooCommerce checkout
 * keeps its own rules.
 */
class CheckoutDifferentAddress {

    use SingletonTrait;

    /** Posted as 1 while the shopper keeps billing the same as shipping. */
    public const SAME_BILLING_FIELD = 'yaycheckout_same_billing';

    /** The address keys copied from shipping_<key> into billing_<key>. billing_email is never copied. */
    public const COPIED_KEYS = [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ];

    /** After CheckoutRequiredFields (9999), so its required flags don't put billing's back. */
    private const FIELDS_PRIORITY = 10000;

    protected function __construct() {
        // Not gated on the page: checkout POST validates at wp_loaded, before is_page() works.
        add_filter( 'woocommerce_checkout_posted_data', [ $this, 'filter_posted_data' ] );
        add_filter( 'woocommerce_checkout_fields', [ $this, 'filter_fields' ], self::FIELDS_PRIORITY );
    }

    /**
     * WooCommerce's Shipping destination, read on every call: 'billing' (billing is main),
     * 'shipping' (shipping is main) or 'billing_only' (shipping is forced to billing).
     */
    public static function destination(): string {
        if ( wc_ship_to_billing_address_only() ) {
            return 'billing_only';
        }

        return 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 'shipping' : 'billing';
    }

    /** Whether the checkout asks for a shipping address at all; decided when the page is printed. */
    public static function needs_shipping_address(): bool {
        if ( 'billing_only' === self::destination() ) {
            return false;
        }

        return ! ( function_exists( 'WC' ) && WC()->cart ) || WC()->cart->needs_shipping_address();
    }

    /** A logged-in customer whose saved billing and shipping addresses are both set and differ. */
    public static function saved_addresses_differ(): bool {
        if ( ! is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->customer ) {
            return false;
        }

        $billing  = WC()->customer->get_billing();
        $shipping = WC()->customer->get_shipping();

        if ( empty( $billing['address_1'] ) || empty( $shipping['address_1'] ) ) {
            return false;
        }

        foreach ( [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ] as $key ) {
            if ( (string) ( $billing[ $key ] ?? '' ) !== (string) ( $shipping[ $key ] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }

    public function filter_posted_data( $data ) {
        if ( ! is_array( $data ) || ! $this->copies() ) {
            return $data;
        }

        foreach ( self::COPIED_KEYS as $key ) {
            $data[ 'billing_' . $key ] = $data[ 'shipping_' . $key ] ?? '';
        }

        return $data;
    }

    public function filter_fields( $fields ) {
        if ( ! is_array( $fields ) || ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) || ! $this->copies() ) {
            return $fields;
        }

        foreach ( self::COPIED_KEYS as $key ) {
            if ( isset( $fields['billing'][ 'billing_' . $key ] ) && is_array( $fields['billing'][ 'billing_' . $key ] ) ) {
                $fields['billing'][ 'billing_' . $key ]['required'] = false;
            }
        }

        return $fields;
    }

    /** Shipping is main, a layout is served, and the shopper posted "same address for billing". */
    private function copies(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before it reads posted data.
        $same = isset( $_POST[ self::SAME_BILLING_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::SAME_BILLING_FIELD ] ) ) : '';

        return '1' === $same
            && 'shipping' === self::destination()
            && CheckoutPage::get_instance()->layout() instanceof Layout;
    }
}
