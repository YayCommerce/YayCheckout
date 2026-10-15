<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\RequiredFieldOverlay;

defined( 'ABSPATH' ) || exit;

/**
 * Why a checkout field or address section is out of use for this request: the country locale hides it,
 * billing copies shipping, or the order uses no such address. A new reason belongs here, read by the
 * classes that act on it. Read on every call, so a value WooCommerce changes mid-request counts. No hooks.
 */
final class CheckoutFieldUsage {

    /**
     * Billing and shipping keys the country locale hides, for the country WooCommerce built
     * each fieldset for (WC_Checkout::initialize_checkout_fields()).
     *
     * @param array $fields WooCommerce checkout fields, by fieldset.
     * @return string[]
     */
    public static function locale_hidden_keys( array $fields ): array {
        if ( ! function_exists( 'WC' ) ) {
            return [];
        }

        $hidden = [];

        foreach ( RequiredFieldOverlay::ADDRESS_FIELDSETS as $fieldset ) {
            if ( empty( $fields[ $fieldset ] ) || ! is_array( $fields[ $fieldset ] ) ) {
                continue;
            }

            $country = WC()->checkout()->get_value( $fieldset . '_country' );
            $country = is_string( $country ) && '' !== $country ? $country : WC()->countries->get_base_country();

            foreach ( array_keys( $fields[ $fieldset ] ) as $key ) {
                if ( CheckoutFieldDefaults::hidden_for_country( (string) $key, $country ) ) {
                    $hidden[] = (string) $key;
                }
            }
        }

        return $hidden;
    }

    /**
     * Billing keys the posted shipping address replaces, so the shopper never fills them in; none
     * unless CheckoutDifferentAddress::billing_copied().
     *
     * @return string[]
     */
    public static function copied_billing_keys(): array {
        if ( ! CheckoutDifferentAddress::billing_copied() ) {
            return [];
        }

        return array_map( static fn( $key ) => 'billing_' . $key, CheckoutDifferentAddress::COPIED_KEYS );
    }

    /**
     * The address sections the order doesn't use, as WooCommerce ignores their own fields: Shipping
     * when the cart needs no shipping address or the shopper keeps one address, Billing when it copies Shipping.
     *
     * @param array $data WooCommerce's posted checkout data, after woocommerce_checkout_posted_data.
     * @return string[] 'billing' and/or 'shipping'.
     */
    public static function unused_sections( array $data ): array {
        $unused = [];

        if ( ! CheckoutDifferentAddress::needs_shipping_address() || empty( $data['ship_to_different_address'] ) ) {
            $unused[] = 'shipping';
        }

        if ( CheckoutDifferentAddress::billing_copied() ) {
            $unused[] = 'billing';
        }

        return $unused;
    }
}
