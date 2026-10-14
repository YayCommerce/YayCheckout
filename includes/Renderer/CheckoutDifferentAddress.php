<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutMarkupReader;
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

    /**
     * What the Different address and Shipping blocks print for the served layout, decided once so
     * WooCommerce's box is never printed twice, or not at all:
     * - block: 'ship' (WooCommerce's "Ship to a different address?" box), 'same' ("Use same address
     *   for billing") or '' (nothing: no Shipping section, or shipping is main with no Billing to replace);
     * - wc_box: Shipping prints WooCommerce's box checked and hidden, because nothing else decides it;
     * - same_marker: Shipping posts SAME_BILLING_FIELD=1, because there is no Billing section to keep.
     * With no layout, or no shipping address needed, nothing prints.
     *
     * @return array{block: string, wc_box: bool, same_marker: bool}
     */
    public static function render_plan( ?Layout $layout ): array {
        if ( null === $layout || ! self::needs_shipping_address() ) {
            return [
                'block'       => '',
                'wc_box'      => false,
                'same_marker' => false,
            ];
        }

        $shipping_is_main = 'shipping' === self::destination();
        $has_billing      = LayoutMarkupReader::has_billing( $layout->content );
        $has_block        = LayoutMarkupReader::has_different_address( $layout->content );
        $block            = '';

        if ( $has_block && LayoutMarkupReader::has_shipping( $layout->content ) ) {
            if ( ! $shipping_is_main ) {
                $block = 'ship';
            } elseif ( $has_billing ) {
                $block = 'same';
            }
        }

        return [
            'block'       => $block,
            'wc_box'      => $shipping_is_main || ! $has_block,
            'same_marker' => $shipping_is_main && ! $has_billing,
        ];
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

        // Phone is contact detail, not address.
        foreach ( array_diff( self::COPIED_KEYS, [ 'phone' ] ) as $key ) {
            if ( (string) ( $billing[ $key ] ?? '' ) !== (string) ( $shipping[ $key ] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }

    public function filter_posted_data( $data ) {
        if ( ! is_array( $data ) || ! self::billing_copied() ) {
            return $data;
        }

        foreach ( self::COPIED_KEYS as $key ) {
            $data[ 'billing_' . $key ] = $data[ 'shipping_' . $key ] ?? '';
        }

        return $data;
    }

    public function filter_fields( $fields ) {
        if ( ! is_array( $fields ) || ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) || ! self::billing_copied() ) {
            return $fields;
        }

        foreach ( CheckoutFieldUsage::copied_billing_keys() as $key ) {
            if ( isset( $fields['billing'][ $key ] ) && is_array( $fields['billing'][ $key ] ) ) {
                $fields['billing'][ $key ]['required'] = false;
            }
        }

        return $fields;
    }

    /**
     * Shipping is main, a layout is served, and the shopper posted "same address for billing", so the
     * Billing section isn't used for this request. CheckoutExtraFields skips the extra fields inside it.
     */
    public static function billing_copied(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before it reads posted data.
        $same = isset( $_POST[ self::SAME_BILLING_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::SAME_BILLING_FIELD ] ) ) : '';

        return '1' === $same
            && 'shipping' === self::destination()
            && CheckoutPage::get_instance()->layout() instanceof Layout;
    }
}
