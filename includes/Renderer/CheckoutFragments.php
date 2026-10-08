<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping, payment and order summary HTML for render.php and update_order_review fragments,
 * plus the Place order button when it shows the order total.
 *
 * Stands down without a layout so Woo's payment fragment keeps Place order.
 */
class CheckoutFragments {

    use SingletonTrait;

    public const SHIPPING_RATES_CLASS = 'yaycheckout-shipping-methods__rates';

    public const ORDER_ITEMS_CLASS = 'yaycheckout-order-summary-items';

    public const ORDER_TOTALS_CLASS = 'yaycheckout-order-summary-totals';

    /** Layout slot => [ selector WooCommerce's checkout.js replaces, method building its HTML ]. */
    private const FRAGMENTS = [
        'payment'          => [ '.woocommerce-checkout-payment', 'payment_methods_html' ],
        'shipping_methods' => [ '.' . self::SHIPPING_RATES_CLASS, 'shipping_rates_html' ],
    ];

    /** Order Summary child block => [ selector checkout.js replaces, method building its HTML ]; found by block name, not slot. */
    private const BLOCK_FRAGMENTS = [
        'yaycheckout/order-summary-items'  => [ '.' . self::ORDER_ITEMS_CLASS, 'order_items_html' ],
        'yaycheckout/order-summary-totals' => [ '.' . self::ORDER_TOTALS_CLASS, 'order_totals_html' ],
    ];

    /** Woo's update_order_review fragment for its whole review-order table. */
    private const REVIEW_ORDER_TABLE = '.woocommerce-checkout-review-order-table';

    protected function __construct() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's template path filter.
        add_filter( 'wc_get_template', [ $this, 'skip_review_order_table' ], 10, 2 );
    }

    /**
     * During update_order_review, Woo renders its whole review-order table before the fragments
     * filter runs. A layout with Items or Totals discards it, so swap it for an empty template
     * and its review-order hooks fire once, in our tables.
     */
    public function skip_review_order_table( $template, $template_name ) {
        if ( 'checkout/review-order.php' !== $template_name || ! doing_action( 'wc_ajax_update_order_review' ) ) {
            return $template;
        }

        $layout = CheckoutPage::get_instance()->layout();

        if ( null === $layout || empty( self::summary_block_fragments( $layout->content ) ) ) {
            return $template;
        }

        return YAYCHECKOUT_PLUGIN_DIR . 'templates/review-order-skipped.php';
    }

    /** Swaps the fragments of this layout's sections for this layout's HTML, and refreshes Place order's total. */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout ) {
            return $fragments;
        }

        $rendered = array_flip( LayoutMarkupReader::slots( $layout->content ) );
        $summary  = self::summary_block_fragments( $layout->content );

        if ( ! empty( $summary ) ) {
            // Empty after skip_review_order_table(), and no element on the page matches it.
            unset( $fragments[ self::REVIEW_ORDER_TABLE ] );
        }

        foreach ( array_merge( array_values( array_intersect_key( self::FRAGMENTS, $rendered ) ), $summary ) as [ $selector, $build ] ) {
            $html = self::$build();

            if ( '' !== $html ) {
                $fragments[ $selector ] = $html;
            }
        }

        $button = isset( $rendered['place_order'] ) ? self::place_order_button_html( $layout->content ) : '';

        if ( '' !== $button ) {
            $fragments['#place_order'] = $button;
        }

        return $fragments;
    }

    /**
     * The BLOCK_FRAGMENTS entries whose block is in the layout.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function summary_block_fragments( string $markup ): array {
        $names = array_column( LayoutMarkupReader::flatten( parse_blocks( $markup ) ), 'blockName' );

        return array_values( array_intersect_key( self::BLOCK_FRAGMENTS, array_flip( array_filter( $names, 'is_string' ) ) ) );
    }

    /** The Place order button alone (render.php prints only it during AJAX), when it shows the total. */
    private static function place_order_button_html( string $markup ): string {
        foreach ( LayoutMarkupReader::flatten( parse_blocks( $markup ) ) as $block ) {
            if ( 'yaycheckout/place-order' === $block['blockName'] ) {
                return empty( $block['attrs']['showTotal'] ) ? '' : render_block( $block );
            }
        }

        return '';
    }

    public static function print_shipping_rates(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for rate radios.
        echo self::shipping_rates_html();
    }

    public static function print_payment_methods(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for gateways.
        echo self::payment_methods_html();
    }

    /** Shipping methods is empty when the cart needs no shipping; the "enter your address" and "no options" messages count as content. */
    public static function shipping_empty(): bool {
        return ! WC()->cart instanceof \WC_Cart || ! ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() );
    }

    /** Payment is empty when the order needs no payment; the "no available payment methods" notice counts as content. */
    public static function payment_empty(): bool {
        return ! WC()->cart instanceof \WC_Cart || ! WC()->cart->needs_payment();
    }

    /** HideWhenEmpty's markers for a fragment root, so the live script reads emptiness from the swapped HTML. */
    private static function live_attributes( bool $is_empty ): string {
        return ' ' . HideWhenEmpty::LIVE_ATTR . '="1"' . ( $is_empty ? ' ' . HideWhenEmpty::EMPTY_ATTR . '="1"' : '' );
    }

    /**
     * Rates wrapper so AJAX can replace an empty first paint. Fires Woo's review-order shipping
     * hooks around the rates, as review-order.php does, since Totals leaves the rates to this block.
     */
    public static function shipping_rates_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $empty = self::shipping_empty();

        ob_start();

        echo '<div class="' . esc_attr( self::SHIPPING_RATES_CLASS ) . '"' . self::live_attributes( $empty ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- live_attributes() prints fixed names and values.

        if ( ! $empty ) {
            echo '<table class="shop_table"><tbody>';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's review-order hook.
            do_action( 'woocommerce_review_order_before_shipping' );
            wc_cart_totals_shipping_html();
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's review-order hook.
            do_action( 'woocommerce_review_order_after_shipping' );
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
<div id="payment" class="woocommerce-checkout-payment"<?php echo self::live_attributes( self::payment_empty() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- live_attributes() prints fixed names and values. ?>>
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

    /** An Items or Totals block: its wrapper and the table fragments() refreshes. */
    public static function print_summary_block( \WP_Block $block ): void {
        if ( ! isset( self::BLOCK_FRAGMENTS[ $block->name ] ) ) {
            return;
        }

        $build = self::BLOCK_FRAGMENTS[ $block->name ][1];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
        echo '<div ' . get_block_wrapper_attributes() . '>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for review-order rows.
        echo self::$build();
        echo '</div>';
    }

    /**
     * The thead and tbody of Woo's checkout/review-order.php, without its table class so
     * Woo's own review-order fragment cannot replace it with the whole table.
     */
    public static function order_items_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Woo's review-order hooks, filters and strings, so its translation still applies.
        ob_start();
        ?>
<table class="shop_table <?php echo esc_attr( self::ORDER_ITEMS_CLASS ); ?>">
    <thead>
        <tr>
            <th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        do_action( 'woocommerce_review_order_before_cart_contents' );

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

            if ( ! $product instanceof \WC_Product || ! $product->exists() || $cart_item['quantity'] <= 0 || ! $visible ) {
                continue;
            }
            ?>
        <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
            <td class="product-name">
                <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
                <?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's filtered HTML. ?>
                <?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo escapes item data. ?>
            </td>
            <td class="product-total">
                <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's price HTML. ?>
            </td>
        </tr>
            <?php
        }

        do_action( 'woocommerce_review_order_after_cart_contents' );
        ?>
    </tbody>
</table>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch

        return (string) ob_get_clean();
    }

    /**
     * The tfoot rows of Woo's checkout/review-order.php, as the body of their own table, without
     * the shipping rates: the Shipping Methods block offers those, and Total still counts them.
     */
    public static function order_totals_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Woo's review-order hooks and strings, so its translation still applies.
        ob_start();
        ?>
<table class="shop_table <?php echo esc_attr( self::ORDER_TOTALS_CLASS ); ?>">
    <tbody>
        <tr class="cart-subtotal">
            <th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
            <td><?php wc_cart_totals_subtotal_html(); ?></td>
        </tr>
        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
        <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
            <td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
        <tr class="fee">
            <th><?php echo esc_html( $fee->name ); ?></th>
            <td><?php wc_cart_totals_fee_html( $fee ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
            <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
        <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <th><?php echo esc_html( $tax->label ); ?></th>
            <td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
        </tr>
                <?php endforeach; ?>
            <?php else : ?>
        <tr class="tax-total">
            <th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
            <td><?php wc_cart_totals_taxes_total_html(); ?></td>
        </tr>
            <?php endif; ?>
        <?php endif; ?>
        <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
        <tr class="order-total">
            <th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
            <td><?php wc_cart_totals_order_total_html(); ?></td>
        </tr>
        <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
    </tbody>
</table>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch

        return (string) ob_get_clean();
    }
}
