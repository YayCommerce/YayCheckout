<?php
namespace YayCheckout\Integrations;

use YayCheckout\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Fixes for YayCurrency, active only while that plugin is.
 *
 * - A Visibility rule's subtotal amount is typed in the store's currency, but while the shopper pays in
 *   another one the cart's subtotal is in that currency: ¥32,750 for a $263 cart passes "subtotal < 500"
 *   as 32,750 and hides the block. The subtotal the rules compare is given back in the store's currency.
 * - While the shopper's currency can't be paid in, YayCurrency keeps the cart in the store's currency and
 *   prints WooCommerce's amounts in its fallback currency, with the shopper's beside them. The amounts
 *   YayCheckout formats itself get the same, or they print the store's number with the fallback's symbol.
 */
class YayCurrency {

    use SingletonTrait;

    protected function __construct() {
        if ( ! defined( 'YAY_CURRENCY_VERSION' ) ) {
            return;
        }

        if ( class_exists( YayCurrencyHelper::class ) && class_exists( SupportHelper::class ) ) {
            add_filter( 'yaycheckout_visibility_subtotal', [ $this, 'store_currency_subtotal' ] );
            add_filter( 'yaycheckout_price_html', [ $this, 'fallback_price_html' ], 10, 4 );
        }
    }

    /**
     * An amount as YayCurrency prints WooCommerce's while the shopper pays in its fallback currency, as in its
     * WooCommerceCurrency::woocommerce_cart_subtotal(): the fallback currency's price, then the shopper's in "(~…)".
     *
     * @param string           $html    The amount's HTML as YayCheckout formats it.
     * @param float            $amount  The amount, in the store's currency while the shopper pays in the fallback one.
     * @param string           $key     A live value's key, 'unit_price', 'coupon', or ''.
     * @param \WC_Product|null $product The product, for 'unit_price'.
     */
    public function fallback_price_html( $html, $amount, $key = '', $product = null ): string {
        $html = (string) $html;

        if ( ! YayCurrencyHelper::should_show_approximate_price() ) {
            return $html;
        }

        $current = YayCurrencyHelper::detect_current_currency();

        if ( ! $current || ! YayCurrencyHelper::is_dis_checkout_diff_currency( $current ) ) {
            return $html;
        }

        $currencies = YayCurrencyHelper::get_current_and_fallback_currency( $current );

        if ( empty( $currencies['fallback_currency'] ) || YayCurrencyHelper::is_current_fallback_currency( $currencies ) ) {
            return $html;
        }

        // The cart's subtotal and total as YayCurrency prints their rows, so a currency that rounds each line
        // can't put the two a rounding step apart.
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filters, which YayCurrency hooks.
        if ( 'cart_total' === $key ) {
            return (string) apply_filters( 'woocommerce_cart_total', $html );
        }

        if ( 'cart_subtotal' === $key && WC()->cart instanceof \WC_Cart ) {
            return (string) apply_filters( 'woocommerce_cart_subtotal', $html, false, WC()->cart );
        }

        // One of the product, as YayCurrency prints the line's subtotal: a price fixed per currency stays fixed.
        if ( 'unit_price' === $key && $product instanceof \WC_Product && WC()->cart instanceof \WC_Cart ) {
            return (string) apply_filters( 'woocommerce_cart_product_subtotal', $html, $product, 1, WC()->cart );
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $paid = YayCurrencyHelper::calculate_price_by_currency_html( $currencies['fallback_currency'], (float) $amount );

        if ( ! SupportHelper::display_approximately_converted_price( $current ) ) {
            return $paid;
        }

        // A discount as YayCurrency converts a coupon's, without its rounding, so a chip matches its row.
        $shown = in_array( $key, [ 'coupon', 'cart_discount_total' ], true )
            ? YayCurrencyHelper::format_price( YayCurrencyHelper::calculate_price_by_currency( (float) $amount, true, $current ) )
            : YayCurrencyHelper::calculate_price_by_currency_html( $current, (float) $amount );

        if ( SupportHelper::display_approximate_price_checkout_only() ) {
            return $shown;
        }

        // No tax label: these amounts sit in a row, a sentence or a button label that says what they include.
        return $paid . YayCurrencyHelper::converted_approximately_html( $shown, true );
    }

    /**
     * The cart's subtotal in the store's currency: the subtotal as the cart shows it (with or without tax, after
     * what plugins did to the lines' prices) back at YayCurrency's rate. YayCurrency compares its own minimums
     * (free shipping, coupons) the same way, the minimum times the rate against the cart.
     *
     * @param float $subtotal The cart's subtotal as it shows, in the cart's currency.
     */
    public function store_currency_subtotal( $subtotal ): float {
        $subtotal = (float) $subtotal;
        $current  = YayCurrencyHelper::detect_current_currency();

        // The cart is in the store's currency: that currency picked, or the shopper paying in the fallback one,
        // where YayCurrency keeps the cart in the store's currency and only prints it in another.
        if ( empty( $current['currency'] ) || get_option( 'woocommerce_currency' ) === $current['currency'] || YayCurrencyHelper::is_dis_checkout_diff_currency( $current ) ) {
            return $subtotal;
        }

        return round( (float) YayCurrencyHelper::reverse_calculate_price_by_currency( $subtotal, $current ), (int) get_option( 'woocommerce_price_num_decimals', 2 ) );
    }
}
