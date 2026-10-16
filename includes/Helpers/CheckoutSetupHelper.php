<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce setup gaps that leave the Shipping methods or Payment block with
 * nothing to offer: read by the storefront's merchant-only hints and by the
 * blocks' editor warnings.
 */
class CheckoutSetupHelper {

    /** Shipping is on but no method exists, so WooCommerce's needs_shipping() gives up and the cart ships nothing. */
    public static function shipping_needs_setup(): bool {
        return wc_shipping_enabled() && 0 === wc_get_shipping_method_count( true );
    }

    /** No payment gateway is enabled, so no order can be placed. */
    public static function payment_needs_setup(): bool {
        foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
            if ( 'yes' === $gateway->enabled ) {
                return false;
            }
        }

        return true;
    }

    /** True for a cart holding an item that would ship, whatever the store's shipping setup. */
    public static function cart_has_shippable_item(): bool {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart_contents() as $values ) {
            if ( isset( $values['data'] ) && $values['data'] instanceof \WC_Product && $values['data']->needs_shipping() ) {
                return true;
            }
        }

        return false;
    }

    public static function shipping_settings_url(): string {
        return admin_url( 'admin.php?page=wc-settings&tab=shipping' );
    }

    public static function payment_settings_url(): string {
        return admin_url( 'admin.php?page=wc-settings&tab=checkout' );
    }
}
