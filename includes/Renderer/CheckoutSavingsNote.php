<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Sale + coupon + shipping savings for the Savings note block.
 *
 * Sale is regular vs line_subtotal (before coupons). Coupon is the cart discount total.
 * Shipping is the cheapest paid rate minus the chosen rate, per package.
 */
class CheckoutSavingsNote {

    /** Mirrors the editor placeholder in packages/blocks/src/savings-note/edit.tsx. */
    public const DEFAULT_TEXT = "You're saving %s";

    /**
     * Sale + coupon + shipping for the current cart. 0.0 without a cart.
     */
    public static function amount(): float {
        if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
            return 0.0;
        }

        return self::sale_savings() + self::coupon_savings() + self::shipping_savings();
    }

    /** True when amount() rounds to 0 at the store's price decimals. */
    public static function is_zero(): bool {
        return 0.0 === round( self::amount(), wc_get_price_decimals() );
    }

    /**
     * Escaped sentence with every %s replaced by the formatted amount(). A sentence with no %s
     * uses DEFAULT_TEXT.
     *
     * @param array $attributes Block attributes.
     */
    public static function sentence_html( array $attributes ): string {
        // Translators: %s is the formatted savings amount.
        $fallback = __( "You're saving %s", 'yaycheckout' );
        $text     = LayoutMarkupReader::plain_text( $attributes, 'text', $fallback );

        if ( false === strpos( $text, '%s' ) ) {
            $text = $fallback;
        }

        $price = CheckoutFragments::price_html( self::amount() );
        $parts = explode( '%s', $text );
        $html  = '';

        foreach ( $parts as $i => $part ) {
            $html .= esc_html( $part );

            if ( $i < count( $parts ) - 1 ) {
                $html .= $price;
            }
        }

        return $html;
    }

    /** Regular price minus line_subtotal (plus tax when the cart shows prices with tax), per visible line. */
    private static function sale_savings(): float {
        $cart = WC()->cart;
        $incl = $cart->display_prices_including_tax();
        $sum  = 0.0;

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's review-order filters, same as CheckoutFragments::order_items_html().
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

            if ( ! $product instanceof \WC_Product || ! $product->exists() || $cart_item['quantity'] <= 0 || ! $visible ) {
                continue;
            }

            $regular = $product->get_regular_price();

            if ( ! is_numeric( $regular ) ) {
                continue;
            }

            $qty  = (int) $cart_item['quantity'];
            $args = [
                'qty'   => $qty,
                'price' => (float) $regular,
            ];

            $regular_display = $incl
                ? (float) wc_get_price_including_tax( $product, $args )
                : (float) wc_get_price_excluding_tax( $product, $args );

            $paid = (float) $cart_item['line_subtotal'] + ( $incl ? (float) $cart_item['line_subtotal_tax'] : 0.0 );
            $sum += max( 0.0, $regular_display - $paid );
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        return $sum;
    }

    /** Cart coupon discount, with tax when the cart shows prices with tax. */
    private static function coupon_savings(): float {
        $cart = WC()->cart;
        $sum  = (float) $cart->get_discount_total();

        if ( $cart->display_prices_including_tax() ) {
            $sum += (float) $cart->get_discount_tax();
        }

        return $sum;
    }

    /**
     * Per package: cheapest paid rate minus the chosen rate. No chosen rate, or no paid rate, adds 0.
     */
    private static function shipping_savings(): float {
        if ( ! WC()->shipping() ) {
            return 0.0;
        }

        $packages = WC()->shipping()->get_packages();
        $packages = is_array( $packages ) ? $packages : [];
        $chosen   = WC()->session ? WC()->session->get( 'chosen_shipping_methods', [] ) : [];
        $chosen   = is_array( $chosen ) ? $chosen : [];
        $incl     = WC()->cart->display_prices_including_tax();
        $decimals = wc_get_price_decimals();
        $sum      = 0.0;

        foreach ( $packages as $index => $package ) {
            if ( ! is_array( $package ) ) {
                continue;
            }

            $methods   = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : [];
            $chosen_id = isset( $chosen[ $index ] ) ? $chosen[ $index ] : '';

            if ( '' === $chosen_id ) {
                continue;
            }

            $chosen_cost   = null;
            $cheapest_paid = null;

            foreach ( $methods as $rate ) {
                if ( ! $rate instanceof \WC_Shipping_Rate ) {
                    continue;
                }

                $cost = (float) $rate->get_cost();

                if ( $incl ) {
                    $taxes = $rate->get_taxes();
                    $cost += is_array( $taxes ) ? (float) array_sum( $taxes ) : 0.0;
                }

                if ( $rate->id === $chosen_id ) {
                    $chosen_cost = $cost;
                }

                if ( 0.0 < round( $cost, $decimals ) ) {
                    if ( null === $cheapest_paid || $cost < $cheapest_paid ) {
                        $cheapest_paid = $cost;
                    }
                }
            }

            if ( null === $chosen_cost || null === $cheapest_paid ) {
                continue;
            }

            $sum += max( 0.0, $cheapest_paid - $chosen_cost );
        }

        return $sum;
    }
}
