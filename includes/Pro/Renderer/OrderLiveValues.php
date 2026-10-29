<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Renderer\CheckoutExtraFields;
use YayCheckout\Renderer\LiveValues;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: the live values of a thank-you page, read from its order. The cart_* keys mirror the order's totals
 * and counts, the order_* keys are its overview, and `field:<key>` spans read its addresses or extra-field
 * snapshot. ThankYouPage hands them to core's LiveValues through yaycheckout_live_values_source.
 */
class OrderLiveValues {

    /**
     * Adds the order-received keys to the live value picker (yaycheckout_live_value_labels). They fill only on
     * a thank-you page, and stay empty on the checkout. Their `order_` prefix makes the picker's Order
     * received group (packages/blocks/src/live-value/popover.tsx).
     *
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    public static function labels( $labels ): array {
        return array_merge(
            is_array( $labels ) ? $labels : [],
            [
                'order_number'         => __( 'Order number', 'yaycheckout' ),
                'order_date'           => __( 'Order date', 'yaycheckout' ),
                'order_email'          => __( 'Email', 'yaycheckout' ),
                'order_payment_method' => __( 'Payment method', 'yaycheckout' ),
            ]
        );
    }

    /**
     * The cart_* totals and counts mirrored from a placed order, plus its overview keys.
     *
     * @return array<string, array{text: string, empty: bool}>
     */
    public static function values( \WC_Order $order ): array {
        $item_count = 0;

        foreach ( $order->get_items() as $item ) {
            $item_count += (int) $item->get_quantity();
        }

        $created = $order->get_date_created();
        $date    = $created ? wc_format_datetime( $created ) : '';
        $number  = (string) $order->get_order_number();
        $email   = (string) $order->get_billing_email();
        $payment = wp_strip_all_tags( (string) $order->get_payment_method_title() );

        return array_merge(
            LiveValues::format_money( self::money( $order ) ),
            LiveValues::format_counts(
                [
                    'cart_item_count' => $item_count,
                    'cart_line_count' => count( $order->get_items() ),
                ]
            ),
            [
                'order_number'         => [
                    'text'  => $number,
                    'empty' => '' === $number,
                ],
                'order_date'           => [
                    'text'  => $date,
                    'empty' => '' === $date,
                ],
                'order_email'          => [
                    'text'  => $email,
                    'empty' => '' === $email,
                ],
                'order_payment_method' => [
                    'text'  => $payment,
                    'empty' => '' === $payment,
                ],
            ]
        );
    }

    /**
     * A shopper field from the order: billing/shipping getters, or an extra-field snapshot row. Null for a
     * field the order doesn't hold, which LiveValues prints as unknown.
     *
     * @return array{text: string, empty: bool, known: bool}|null
     */
    public static function field( \WC_Order $order, string $field ): ?array {
        $text = self::address_text( $order, $field );

        if ( null !== $text ) {
            return [
                'text'  => $text,
                'empty' => '' === $text,
                'known' => true,
            ];
        }

        $rows = $order->get_meta( CheckoutExtraFields::META_SNAPSHOT );

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) || ( $row['key'] ?? '' ) !== $field ) {
                    continue;
                }

                $display = isset( $row['display'] ) && is_scalar( $row['display'] ) ? trim( (string) $row['display'] ) : '';

                return [
                    'text'  => $display,
                    'empty' => '' === $display,
                    'known' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Order money amounts using the same tax display mode as the cart totals live values.
     *
     * @return array<string, float>
     */
    private static function money( \WC_Order $order ): array {
        $tax = self::display_prices_including_tax();

        return [
            'cart_total'          => (float) $order->get_total( 'edit' ),
            // get_cart_tax(): item/fee tax, excluding shipping — closest to cart get_subtotal_tax() for display.
            'cart_subtotal'       => (float) $order->get_subtotal() + ( $tax ? (float) $order->get_cart_tax() : 0.0 ),
            'cart_shipping_total' => (float) $order->get_shipping_total() + ( $tax ? (float) $order->get_shipping_tax() : 0.0 ),
            'cart_discount_total' => (float) $order->get_discount_total() + ( $tax ? (float) $order->get_discount_tax() : 0.0 ),
            'cart_tax_total'      => (float) $order->get_total_tax(),
        ];
    }

    /**
     * Plain text for a billing_/shipping_ order field, with country/state names like ShowableFields.
     * Null when $field is not an order address getter.
     */
    private static function address_text( \WC_Order $order, string $field ): ?string {
        // Only address-style getters: avoid get_id(), get_total(), etc. via a stray field: key.
        if ( 0 !== strpos( $field, 'billing_' ) && 0 !== strpos( $field, 'shipping_' ) ) {
            return null;
        }

        $method = 'get_' . $field;

        if ( ! is_callable( [ $order, $method ] ) ) {
            return null;
        }

        $raw   = $order->$method();
        $value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

        if ( '' === $value || ! function_exists( 'WC' ) || ! WC()->countries instanceof \WC_Countries ) {
            return $value;
        }

        $names = [];

        if ( '_country' === substr( $field, -8 ) ) {
            $names = WC()->countries->get_countries();
        } elseif ( '_state' === substr( $field, -6 ) ) {
            $prefix         = substr( $field, 0, -6 );
            $country_method = 'get_' . $prefix . '_country';
            $country_code   = is_callable( [ $order, $country_method ] ) ? $order->$country_method() : '';
            $country_code   = is_scalar( $country_code ) ? (string) $country_code : '';
            $names          = '' !== $country_code ? WC()->countries->get_states( $country_code ) : [];
        }

        return is_array( $names ) && isset( $names[ $value ] ) ? $names[ $value ] : $value;
    }

    /** Same tax display mode the cart live values use. */
    private static function display_prices_including_tax(): bool {
        if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
            return WC()->cart->display_prices_including_tax();
        }

        return 'incl' === get_option( 'woocommerce_tax_display_cart' );
    }
}
