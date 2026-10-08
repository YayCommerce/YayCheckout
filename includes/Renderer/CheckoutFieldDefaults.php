<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * What classic checkout gives each address field and Field blocks would otherwise lack:
 * the shopper's value, the classes WooCommerce's checkout.js listens on, and its label.
 * No hooks; the field, email and field-value render.php call it.
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
     * WooCommerce's behaviour classes for a billing or shipping key, after locale and
     * third-party woocommerce_checkout_fields changes.
     *
     * @return string[]
     */
    public static function classes( string $key ): array {
        $field = self::field( $key );
        $class = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : [];

        return array_values( array_intersect( $class, self::BEHAVIOUR_CLASSES ) );
    }

    /** WooCommerce's label for a billing or shipping key; empty when it has none. */
    public static function label( string $key ): string {
        $field = self::field( $key );

        return isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : '';
    }

    /**
     * A billing or shipping key's WooCommerce field definition, after woocommerce_checkout_fields.
     *
     * @return array<string, mixed>
     */
    private static function field( string $key ): array {
        if ( ! function_exists( 'WC' ) || ! preg_match( '/^(billing|shipping)_/', $key, $match ) ) {
            return [];
        }

        $fields = WC()->checkout()->get_checkout_fields( $match[1] );

        return isset( $fields[ $key ] ) && is_array( $fields[ $key ] ) ? $fields[ $key ] : [];
    }
}
