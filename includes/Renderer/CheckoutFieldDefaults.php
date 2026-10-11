<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * What classic checkout gives each address field and Field blocks would otherwise lack:
 * the shopper's value, and the classes WooCommerce's checkout.js listens on.
 * No hooks; the field and email render.php call it.
 */
class CheckoutFieldDefaults {

    /** Classes checkout.js reacts to; layout classes such as form-row-first are left to the block. */
    private const BEHAVIOUR_CLASSES = [ 'address-field', 'update_totals_on_change' ];

    /**
     * The value classic checkout prints: the session's, or the account's when logged in.
     * Null outside a storefront request, where there is no customer.
     *
     * @return mixed
     */
    public static function value( string $key ) {
        if ( ! function_exists( 'WC' ) || ! WC()->customer instanceof \WC_Customer ) {
            return null;
        }

        return WC()->checkout()->get_value( $key );
    }

    /**
     * True when WooCommerce's country locale hides this billing or shipping field for the shopper's
     * country (State for VN, Postcode for AE). Reads the locale as address-i18n.js does, with its
     * fallback to `default`: get_address_fields() drops the `hidden` flag.
     *
     * @param string $country Country code to check; empty reads the customer's billing or shipping country.
     */
    public static function hidden_for_country( string $key, string $country = '' ): bool {
        if ( ! function_exists( 'WC' ) || ! preg_match( '/^(billing|shipping)_(.+)$/', $key, $match ) ) {
            return false;
        }

        $country = '' !== $country ? $country : self::value( $match[1] . '_country' );

        if ( ! is_string( $country ) || '' === $country ) {
            return false;
        }

        $locale = WC()->countries->get_country_locale();
        $fields = isset( $locale[ $country ] ) ? $locale[ $country ] : ( isset( $locale['default'] ) ? $locale['default'] : [] );

        return ! empty( $fields[ $match[2] ]['hidden'] );
    }

    /**
     * WooCommerce's behaviour classes for a billing or shipping key, after locale and
     * third-party woocommerce_checkout_fields changes.
     *
     * @return string[]
     */
    public static function classes( string $key ): array {
        if ( ! function_exists( 'WC' ) || ! preg_match( '/^(billing|shipping)_/', $key, $match ) ) {
            return [];
        }

        $fields = WC()->checkout()->get_checkout_fields( $match[1] );
        $class  = isset( $fields[ $key ]['class'] ) && is_array( $fields[ $key ]['class'] ) ? $fields[ $key ]['class'] : [];

        return array_values( array_intersect( $class, self::BEHAVIOUR_CLASSES ) );
    }
}
