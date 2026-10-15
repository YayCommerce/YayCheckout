<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * The Express Pay block's link to the payment gateways. Gateways print their wallet buttons on
 * WooCommerce's classic checkout hook below, and the block fires it where the merchant placed it.
 */
class ExpressPay {

    /** WooCommerce prints wc_get_pay_buttons() here; Stripe prints its Express Checkout container here. */
    public const HOOK = 'woocommerce_checkout_before_customer_details';

    /** Stripe's classic Express Checkout script, which stands down while its params say the page holds the Checkout block. */
    private const STRIPE_SCRIPT = 'wc_stripe_express_checkout';

    /** WooCommerce's PaymentGatewayFeature::PAY_BUTTON, written out for versions without the enum. */
    private const PAY_BUTTON = 'pay_button';

    /**
     * Whether an enabled gateway can put buttons in the block: one supports Woo's pay buttons, or something
     * besides wc_get_pay_buttons() hooks HOOK. For the editor's notice, so it errs towards no notice.
     */
    public static function has_buttons(): bool {
        global $wp_filter;

        $callbacks = isset( $wp_filter[ self::HOOK ] ) ? array_merge( ...array_values( $wp_filter[ self::HOOK ]->callbacks ) ) : [];
        $core      = false !== has_action( self::HOOK, 'wc_get_pay_buttons' ) ? 1 : 0;

        if ( count( $callbacks ) > $core ) {
            return true;
        }

        foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
            if ( 'yes' === $gateway->enabled && $gateway->supports( self::PAY_BUTTON ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stripe reads has_block() on the checkout page, which is true while the page holds WooCommerce's Checkout
     * block, and then leaves the buttons to that block. Our template prints in its place, so the classic
     * script must run. Called after Stripe enqueues; does nothing without Stripe.
     */
    public static function run_stripe_classic_script(): void {
        wp_add_inline_script(
            self::STRIPE_SCRIPT,
            'if ( window.wc_stripe_express_checkout_params ) { window.wc_stripe_express_checkout_params.has_block = ""; }',
            'before'
        );
    }
}
