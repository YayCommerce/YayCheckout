<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * The Express Pay block's link to the payment gateways. Gateways print their wallet buttons on
 * WooCommerce's classic checkout hook below, and the block fires it where the merchant placed it.
 */
class ExpressPay {

    public const BLOCK = 'yaycheckout/express-pay';

    /** WooCommerce prints wc_get_pay_buttons() here; Stripe prints its Express Checkout container here. */
    public const HOOK = 'woocommerce_checkout_before_customer_details';

    /** HOOK's pair, fired right after it: a gateway may open markup on HOOK and close it here (PayPal's Fastlane loader). */
    public const AFTER_HOOK = 'woocommerce_checkout_after_customer_details';

    /**
     * Whether an enabled gateway can put buttons in the block: one supports Woo's pay buttons, or something
     * besides wc_get_pay_buttons() hooks HOOK. For the editor's notice, so it errs towards no notice.
     */
    public static function has_buttons(): bool {
        if ( WcHooks::callbacks_besides( self::HOOK, [ 'wc_get_pay_buttons' ] ) > 0 ) {
            return true;
        }

        foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
            // WooCommerce's PaymentGatewayFeature::PAY_BUTTON, written out for versions without the enum.
            if ( 'yes' === $gateway->enabled && $gateway->supports( 'pay_button' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stripe's classic Express Checkout params, filtered by wc_stripe_express_checkout_params. Stripe sets
     * has_block while the checkout page holds WooCommerce's Checkout block and its classic script then leaves
     * the buttons to that block; our template prints in its place, so the script must run.
     *
     * @param mixed $params Stripe's params.
     * @return mixed
     */
    public static function stripe_params( $params ) {
        if ( is_array( $params ) ) {
            $params['has_block'] = false;
        }

        return $params;
    }
}
