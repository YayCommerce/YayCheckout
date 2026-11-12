<?php
namespace YayCheckout\Integrations;

use YayCheckout\Renderer\ExpressPay;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * FunnelKit Stripe's express checkout buttons. Its "inline" checkout position prints them on
 * woocommerce_checkout_billing, which no block fires (the Billing block prints its fields itself),
 * so they move into the Express Pay block. Inert without FunnelKit: adoption finds no callback.
 */
class FunnelKitStripe {

    use SingletonTrait;

    protected function __construct() {
        ExpressPay::adopt( 'woocommerce_checkout_billing', 'FKWCS\Gateway\Stripe\SmartButtons', 'payment_request_button' );
    }
}
