<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Fires one allow-listed WooCommerce checkout hook where a hook block sits.
 *
 * The selectable names match packages/blocks/src/hook/hooks.ts. The two
 * form-edge names belong to their own blocks and are not in that select.
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
     * @param string $hook WooCommerce checkout action name.
     */
    public static function fire( string $hook ): void {
        if ( ! in_array( $hook, self::allowed(), true ) ) {
            return;
        }

        // Admin, REST, and CLI have no storefront cart. Woo callbacks on these hooks read it.
        if ( ! function_exists( 'WC' ) || ! ( WC()->cart instanceof \WC_Cart ) ) {
            return;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- $hook is allow-listed WooCommerce checkout actions.
        do_action( $hook, WC()->checkout() );
    }

    /**
     * @return string[]
     */
    public static function allowed(): array {
        return array_merge( [ self::BEFORE_FORM, self::AFTER_FORM ], self::INSIDE_FORM );
    }
}
