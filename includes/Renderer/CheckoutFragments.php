<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping and payment HTML for render.php and update_order_review fragments.
 *
 * Stands down without a layout so Woo's payment fragment keeps Place order.
 */
class CheckoutFragments {

    use SingletonTrait;

    public const SHIPPING_RATES_CLASS = 'yaycheckout-shipping-methods__rates';

    /** Layout slot => [ selector WooCommerce's checkout.js replaces, method building its HTML ]. */
    private const FRAGMENTS = [
        'payment'          => [ '.woocommerce-checkout-payment', 'payment_methods_html' ],
        'shipping_methods' => [ '.' . self::SHIPPING_RATES_CLASS, 'shipping_rates_html' ],
    ];

    protected function __construct() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
    }

    /**
     * Swap Woo's payment/shipping fragments for this layout's HTML.
     *
     * @param  array $fragments
     * @return array
     */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout ) {
            return $fragments;
        }

        $rendered = array_flip( LayoutMarkupReader::slots( $layout->content ) );

        foreach ( array_intersect_key( self::FRAGMENTS, $rendered ) as [ $selector, $build ] ) {
            $html = self::$build();

            if ( '' !== $html ) {
                $fragments[ $selector ] = $html;
            }
        }

        return $fragments;
    }

    public static function print_shipping_rates(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for rate radios.
        echo self::shipping_rates_html();
    }

    public static function print_payment_methods(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for gateways.
        echo self::payment_methods_html();
    }

    /** Rates wrapper so AJAX can replace an empty first paint. */
    public static function shipping_rates_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        ob_start();

        echo '<div class="' . esc_attr( self::SHIPPING_RATES_CLASS ) . '">';

        if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) {
            echo '<table class="shop_table"><tbody>';
            wc_cart_totals_shipping_html();
            echo '</tbody></table>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /** Gateway radios only; Place order is a separate block. */
    public static function payment_methods_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        if ( WC()->cart->needs_payment() ) {
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            WC()->payment_gateways()->set_current_gateway( $available_gateways );
        } else {
            $available_gateways = [];
        }

        ob_start();

        if ( ! wp_doing_ajax() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_before_payment' );
        }
        ?>
<div id="payment" class="woocommerce-checkout-payment">
        <?php if ( WC()->cart->needs_payment() ) : ?>
    <ul class="wc_payment_methods payment_methods methods" aria-label="<?php esc_attr_e( 'Payment methods', 'yaycheckout' ); ?>">
            <?php
            if ( ! empty( $available_gateways ) ) {
                foreach ( $available_gateways as $gateway ) {
                    wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
                }
            } else {
                echo '<li>';
                wc_print_notice(
                    apply_filters(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
                        'woocommerce_no_available_payment_methods_message',
                        WC()->customer->get_billing_country()
                            ? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'yaycheckout' )
                            : esc_html__( 'Please fill in your details above to see available payment methods.', 'yaycheckout' )
                    ),
                    'notice'
                );
                echo '</li>';
            }
            ?>
    </ul>
        <?php endif; ?>
</div>
        <?php
        if ( ! wp_doing_ajax() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_after_payment' );
        }

        return (string) ob_get_clean();
    }
}
