<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Fires one checkout hook where a hook block sits.
 *
 * WooCommerce names are the fixed list in this class and receive the checkout
 * object only, matching their original signature. Any other name runs with no
 * arguments: checkout and cart are globals, so a callback reads them itself.
 * The selectable WooCommerce names match packages/blocks/src/hook/hooks.ts.
 */
class HookBlockRunHelper {

    public const BEFORE_FORM = 'woocommerce_before_checkout_form';

    public const AFTER_FORM = 'woocommerce_after_checkout_form';

    /** @var string[] Hooks the Hook block may store. */
    public const INSIDE_FORM = [
        'woocommerce_checkout_before_customer_details',
        'woocommerce_checkout_after_customer_details',
        'woocommerce_before_checkout_billing_form',
        'woocommerce_after_checkout_billing_form',
        'woocommerce_before_checkout_shipping_form',
        'woocommerce_after_checkout_shipping_form',
    ];

    /**
     * @param string $hook Checkout action name.
     */
    public static function fire( string $hook ): void {
        if ( '' === $hook ) {
            return;
        }

        // Admin, REST, and CLI have no storefront cart. Woo callbacks on these hooks read it.
        if ( ! function_exists( 'WC' ) || ! ( WC()->cart instanceof \WC_Cart ) ) {
            return;
        }

        if ( in_array( $hook, self::allowed(), true ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Allow-listed WooCommerce checkout action.
            do_action( $hook, WC()->checkout() );
            return;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Merchant-chosen hook name. No arguments; callbacks read WC() themselves.
        do_action( $hook );
    }

    /**
     * @return string[]
     */
    public static function allowed(): array {
        return array_merge( [ self::BEFORE_FORM, self::AFTER_FORM ], self::INSIDE_FORM );
    }
}
