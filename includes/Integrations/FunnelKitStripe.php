<?php
namespace YayCheckout\Integrations;

use YayCheckout\Renderer\ExpressPay;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Moves FunnelKit Stripe's express buttons into the Express Pay block.
 */
class FunnelKitStripe {

    use SingletonTrait;

    protected function __construct() {
        ExpressPay::adopt( 'woocommerce_checkout_billing', 'FKWCS\Gateway\Stripe\SmartButtons', 'payment_request_button' );
    }
}
