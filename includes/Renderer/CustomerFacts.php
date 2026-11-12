<?php
namespace YayCheckout\Renderer;

use YayCheckout\Enums\ConditionType;

defined( 'ABSPATH' ) || exit;

/**
 * The customer facts assignment conditions test: the billing and shipping country.
 */
class CustomerFacts {

    /**
     * The customer's part of the assignment context, keyed by ConditionType. Empty until WooCommerce loads
     * the session, so a country condition stays undecided rather than testing a blank customer.
     * Shipping falls back to billing, and both to the store's default customer location, as the form opens with.
     */
    public static function context(): array {
        if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) || ! WC()->customer instanceof \WC_Customer ) {
            return [];
        }

        $billing  = WC()->customer->get_billing_country();
        $billing  = '' !== $billing ? $billing : (string) ( wc_get_customer_default_location()['country'] ?? '' );
        $shipping = WC()->customer->get_shipping_country();
        $shipping = '' !== $shipping ? $shipping : $billing;

        return array_filter(
            [
                ConditionType::BILLING_COUNTRY  => $billing,
                ConditionType::SHIPPING_COUNTRY => $shipping,
            ],
            static fn( string $country ) => '' !== $country
        );
    }
}
