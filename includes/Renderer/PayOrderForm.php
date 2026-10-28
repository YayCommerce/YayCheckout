<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * The pay-for-order form the checkout blocks are rendering inside, if any. On a pay-order page the Checkout,
 * Payment, Terms, Place order and Express Pay blocks sit inside WooCommerce's pay form (#order_review) instead
 * of the checkout form, and print for the order being paid instead of the cart.
 *
 * The pay-order page is Pro: Pro's PayOrderPage answers yaycheckout_pay_order_form while its form renders.
 * Free has no answer, so current() is null and every block prints as on the checkout.
 */
class PayOrderForm {

    /**
     * The form being rendered: the layout, the order being paid, the gateways WooCommerce offers for it and the pay
     * button's text, as order_pay() passes them to form-pay.php. Null outside a pay form, and always in Free.
     *
     * @return array{layout: Layout, order: \WC_Order, gateways: \WC_Payment_Gateway[], button_text: string}|null
     */
    public static function current(): ?array {
        /**
         * The pay-for-order form the blocks render inside, or null. Pro's pay-order page answers while its
         * form renders.
         *
         * @param null|array{layout: Layout, order: \WC_Order, gateways: \WC_Payment_Gateway[], button_text: string} $form
         */
        $form = apply_filters( 'yaycheckout_pay_order_form', null );

        if ( ! is_array( $form ) || ! ( $form['layout'] ?? null ) instanceof Layout || ! ( $form['order'] ?? null ) instanceof \WC_Order ) {
            return null;
        }

        return [
            'layout'      => $form['layout'],
            'order'       => $form['order'],
            'gateways'    => is_array( $form['gateways'] ?? null ) ? $form['gateways'] : [],
            'button_text' => is_string( $form['button_text'] ?? null ) ? $form['button_text'] : '',
        ];
    }

    /** True while the blocks render inside a pay-for-order form. */
    public static function in_form(): bool {
        return null !== self::current();
    }

    /**
     * Where the Express Pay block sits in a pay form: the pay-order page prints the gateways' express buttons
     * there (woocommerce_pay_order_before_payment), once.
     */
    public static function express_pay(): void {
        /** Fired by the Express Pay block inside a pay-for-order form. */
        do_action( 'yaycheckout_pay_order_express_pay' );
    }
}
