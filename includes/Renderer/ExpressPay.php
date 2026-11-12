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

    /** @var array<int, array{hook: string, class: string, method: string}> Gateway buttons printed on a hook no block fires, see adopt(). */
    private static array $adopted = [];

    /**
     * Whether an enabled gateway can put buttons in the block: one supports Woo's pay buttons, or something
     * besides wc_get_pay_buttons() hooks HOOK, or an adopted callback is attached. For the editor's notice, so it errs towards no notice.
     */
    public static function has_buttons(): bool {
        if ( WcHooks::callbacks_besides( self::HOOK, [ 'wc_get_pay_buttons' ] ) > 0 ) {
            return true;
        }

        foreach ( self::$adopted as $entry ) {
            if ( null !== self::attached( $entry ) ) {
                return true;
            }
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
     * Moves $class_name's $method from $hook to the block when a layout with an Express Pay block is served: for a
     * gateway that prints its wallet buttons on a classic hook no block fires. Names, not a callable, so the
     * gateway isn't constructed early; adopt_into_block() finds its instance on $hook.
     */
    public static function adopt( string $hook, string $class_name, string $method ): void {
        self::$adopted[] = [
            'hook'   => $hook,
            'class'  => $class_name,
            'method' => $method,
        ];
    }

    /** On wp, for a served layout with an Express Pay block. A Hook block that fires the original hook keeps its callback. */
    public static function adopt_into_block(): void {
        foreach ( self::$adopted as $entry ) {
            if ( WcHooks::is_placed( $entry['hook'] ) ) {
                continue;
            }

            $attached = self::attached( $entry );

            if ( null === $attached ) {
                continue;
            }

            remove_action( $entry['hook'], $attached['function'], $attached['priority'] );

            // A gateway that also hooks HOOK would print twice.
            if ( false === has_action( self::HOOK, $attached['function'] ) ) {
                add_action( self::HOOK, $attached['function'], $attached['priority'], $attached['accepted_args'] );
            }
        }
    }

    /**
     * The adopted object callback attached to its hook, or null.
     *
     * @param array{hook: string, class: string, method: string} $entry From adopt().
     * @return array{function: callable, priority: int, accepted_args: int}|null
     */
    private static function attached( array $entry ): ?array {
        global $wp_filter;

        if ( ! isset( $wp_filter[ $entry['hook'] ] ) ) {
            return null;
        }

        foreach ( $wp_filter[ $entry['hook'] ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $function = $callback['function'];

                if ( is_array( $function ) && isset( $function[0], $function[1] ) && is_object( $function[0] ) && is_a( $function[0], $entry['class'] ) && $entry['method'] === $function[1] ) {
                    return [
                        'function'      => $function,
                        'priority'      => (int) $priority,
                        'accepted_args' => (int) $callback['accepted_args'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Whether this checkout request is a wallet payment (Apple Pay, Google Pay, Link) that a gateway's own
     * wc-ajax endpoint sends through WC_Checkout::process_checkout() with only the wallet's fields. Gateways
     * mark it with a POST key: FunnelKit Stripe posts payment_request_type, WooCommerce Stripe's Express
     * Checkout Element express_checkout_type, WooPayments express_payment_type. Filtered by
     * yaycheckout_express_wallet_order_markers. Runs inside WooCommerce's checkout, after the gateway checked its nonce.
     */
    public static function is_wallet_order(): bool {
        $markers = apply_filters( 'yaycheckout_express_wallet_order_markers', [ 'payment_request_type', 'express_checkout_type', 'express_payment_type' ] );

        foreach ( is_array( $markers ) ? $markers : [] as $key ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
            if ( is_string( $key ) && isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) && '' !== sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) {
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
