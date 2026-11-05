<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Renderer\PreviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: what the Save as PDF Button prints. A fixed summary of the thank-you order, in sections the block
 * turns on or off, so only order information reaches the PDF and never the page's other blocks (related
 * products, banners). The block prints it hidden beside the button; save-pdf-button/view.ts moves it under
 * <body>, and its print CSS shows it alone.
 *
 * The gateway's thank-you output (bank details) is not printed here: the page already ran
 * woocommerce_thankyou_<gateway> once, and running it again would repeat a gateway's side effects. This
 * prints an empty slot that view.ts fills with a copy of that output (thankyou-page.php's GATEWAY_CLASS) before printing.
 */
class OrderPrintSummary {

    /** The summary's class; save-pdf-button/view.ts and style.css read it. */
    public const CLASS_NAME = 'yaycheckout-print-summary';

    /** The wrapper thankyou-page.php puts around the gateway's thank-you output. Mirrored in save-pdf-button/view.ts. */
    public const GATEWAY_CLASS = 'yaycheckout-thankyou-gateway';

    /** The slot for the gateway's output. Mirrored in save-pdf-button/view.ts. */
    public const PAYMENT_SLOT = 'data-yaycheckout-print-payment';

    /**
     * Each section's block attribute, all on by default (block.json).
     *
     * @var string[]
     */
    private const SECTIONS = [ 'showLogo', 'showOverview', 'showPaymentInstructions', 'showItems', 'showAddresses', 'showCustomerNote' ];

    /** @var bool Whether this request printed a summary with a payment slot. */
    private static $has_payment_slot = false;

    /**
     * Whether thankyou-page.php should wrap the gateway's output for the payment slot to copy. The blocks render
     * before the gateway hook fires, so a button that rendered (not one hidden, or one that returned early) has set it.
     */
    public static function has_payment_slot(): bool {
        return self::$has_payment_slot;
    }

    /**
     * The summary's markup, hidden until printed.
     *
     * @param \WC_Order $order      The validated thank-you order.
     * @param array     $attributes The Save as PDF Button's attributes.
     */
    public static function markup( \WC_Order $order, array $attributes ): string {
        $on = self::sections( $attributes );

        ob_start();

        /*
         * The page margin is 0 in print (view.ts), to drop the browser's headers and footers, so the margin comes from
         * here: a table whose empty header and footer rows the browser repeats at the top and bottom of every sheet.
         */
        ?>
<div class="<?php echo esc_attr( self::CLASS_NAME ); ?>" aria-hidden="true">
<table class="<?php echo esc_attr( self::CLASS_NAME ); ?>__page" role="presentation">
<thead><tr><td class="<?php echo esc_attr( self::CLASS_NAME ); ?>__margin"></td></tr></thead>
<tfoot><tr><td class="<?php echo esc_attr( self::CLASS_NAME ); ?>__margin"></td></tr></tfoot>
<tbody><tr><td class="<?php echo esc_attr( self::CLASS_NAME ); ?>__body">
    <header class="<?php echo esc_attr( self::CLASS_NAME ); ?>__header">
        <?php if ( $on['showLogo'] ) : ?>
            <div class="<?php echo esc_attr( self::CLASS_NAME ); ?>__shop"><?php echo self::shop_brand(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in shop_brand(). ?></div>
        <?php endif; ?>
        <h1 class="<?php echo esc_attr( self::CLASS_NAME ); ?>__title">
            <?php
            /* translators: %s: order number. */
            echo esc_html( sprintf( __( 'Order #%s', 'yaycheckout' ), $order->get_order_number() ) );
            ?>
        </h1>
    </header>
        <?php
        if ( $on['showOverview'] ) {
            self::print_overview( $order );
        }

        if ( $on['showPaymentInstructions'] ) {
            self::$has_payment_slot = true;
            echo '<section class="' . esc_attr( self::CLASS_NAME ) . '__payment" ' . esc_attr( self::PAYMENT_SLOT ) . '></section>';
        }

        if ( $on['showItems'] ) {
            self::print_items( $order );
        }

        if ( $on['showAddresses'] ) {
            self::print_addresses( $order );
        }

        if ( $on['showCustomerNote'] ) {
            self::print_customer_note( $order );
        }
        ?>
</td></tr></tbody>
</table>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * The section toggles with block.json's defaults; a stored layout leaves an untouched attribute out.
     *
     * @param array $attributes Block attributes.
     * @return array<string, bool>
     */
    private static function sections( array $attributes ): array {
        $on = [];

        foreach ( self::SECTIONS as $name ) {
            $on[ $name ] = ! isset( $attributes[ $name ] ) || (bool) $attributes[ $name ];
        }

        return $on;
    }

    /** The site logo, or the site name when the site has none. */
    private static function shop_brand(): string {
        $logo_id = (int) get_theme_mod( 'custom_logo' );
        // Not lazy: the summary is hidden on screen, so a lazy image would never load before printing.
        $logo = $logo_id > 0
            ? wp_get_attachment_image(
                $logo_id,
                'medium',
                false,
                [
                    'class'   => self::CLASS_NAME . '__logo',
                    'loading' => false,
                ]
            )
            : '';

        return '' !== $logo ? $logo : esc_html( get_bloginfo( 'name' ) );
    }

    /**
     * Order number, date, email, total and payment method, as WooCommerce's thank-you overview lists them. The
     * email shows as there: to the order's signed-in owner only (and on a preview link), never to a guest link's holder.
     */
    private static function print_overview( \WC_Order $order ): void {
        $date  = $order->get_date_created();
        $rows  = [
            __( 'Order number', 'yaycheckout' )   => esc_html( $order->get_order_number() ),
            __( 'Date', 'yaycheckout' )           => $date ? esc_html( wc_format_datetime( $date ) ) : '',
            __( 'Email', 'yaycheckout' )          => self::shows_overview_email( $order ) ? esc_html( $order->get_billing_email() ) : '',
            __( 'Total', 'yaycheckout' )          => wp_kses_post( $order->get_formatted_order_total() ),
            __( 'Payment method', 'yaycheckout' ) => esc_html( $order->get_payment_method_title() ),
        ];
        $class = self::CLASS_NAME . '__overview';

        echo '<dl class="' . esc_attr( $class ) . '">';

        foreach ( $rows as $label => $value ) {
            if ( '' === $value ) {
                continue;
            }

            echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value is escaped above.
        }

        echo '</dl>';
    }

    /** WooCommerce's checkout/thankyou.php gate for the overview's email; a preview link shows the sample order's. */
    private static function shows_overview_email( \WC_Order $order ): bool {
        if ( null !== PreviewRequest::get_instance()->layout_id() ) {
            return true;
        }

        return is_user_logged_in() && $order->get_user_id() === get_current_user_id();
    }

    /**
     * The line items order-details.php lists (`woocommerce_purchase_order_item_types`), with their meta, then WooCommerce's totals rows (subtotal, discounts, shipping, fees, taxes,
     * payment method, refunds, total). Built from the order, not order/order-details.php: that template's
     * actions already ran for the page's Order Details block, and running them again repeats what plugins hook there.
     */
    private static function print_items( \WC_Order $order ): void {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filter, so gift cards and custom line types show as on the page.
        $items = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );

        if ( [] === $items ) {
            return;
        }

        echo '<section class="' . esc_attr( self::CLASS_NAME ) . '__items"><h2>' . esc_html__( 'Order details', 'yaycheckout' ) . '</h2>';
        echo '<table><thead><tr><th>' . esc_html__( 'Product', 'yaycheckout' ) . '</th><th>' . esc_html__( 'Quantity', 'yaycheckout' ) . '</th><th>' . esc_html__( 'Total', 'yaycheckout' ) . '</th></tr></thead><tbody>';

        foreach ( $items as $item_id => $item ) {
            // The filter WooCommerce's order-details-item.php checks: bundles and composites hide their child lines with it.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filter.
            if ( ! $item instanceof \WC_Order_Item || ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
                continue;
            }

            // The raw name, as order-details-item.php passes it, escaped once on output. No link on paper.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filter.
            $name     = apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false );
            $quantity = $item->get_quantity();
            $refunded = $order->get_qty_refunded_for_item( $item_id );
            // As order-details-item.php shows a partly refunded line, so it agrees with the refund rows in the totals.
            $quantity = $refunded ? '<del>' . esc_html( (string) $quantity ) . '</del> <ins>' . esc_html( (string) ( $quantity + $refunded ) ) . '</ins>' : esc_html( (string) $quantity );

            $meta = wc_display_item_meta(
                $item,
                [
                    'echo'      => false,
                    'before'    => '<div class="' . esc_attr( self::CLASS_NAME ) . '__meta">',
                    'after'     => '</div>',
                    'separator' => '<br>',
                ]
            );

            echo '<tr><td>' . wp_kses_post( $name ) . wp_kses_post( $meta ) . '</td>';
            echo '<td>' . $quantity . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
            echo '<td>' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td></tr>';
        }

        echo '</tbody><tfoot>';

        foreach ( $order->get_order_item_totals() as $key => $total ) {
            // Escaped as order-details.php escapes them: the payment method's title is plain text.
            $value = 'payment_method' === $key ? esc_html( $total['value'] ) : wp_kses_post( $total['value'] );

            echo '<tr><th colspan="2">' . wp_kses_post( $total['label'] ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
        }

        echo '</tfoot></table></section>';
    }

    /** Billing address and phone, and shipping address, behind ThankYouPage::shows_customer_details(). The overview has the email. */
    private static function print_addresses( \WC_Order $order ): void {
        if ( ! ThankYouPage::shows_customer_details( $order ) ) {
            return;
        }

        $columns = [];
        $billing = $order->get_formatted_billing_address();

        if ( '' !== $billing ) {
            $phone = $order->get_billing_phone();

            $columns[ __( 'Billing address', 'yaycheckout' ) ] = '' !== $phone ? $billing . '<br>' . esc_html( $phone ) : $billing;
        }

        $shipping = $order->needs_shipping_address() ? $order->get_formatted_shipping_address() : '';

        if ( '' !== $shipping ) {
            $columns[ __( 'Shipping address', 'yaycheckout' ) ] = $shipping;
        }

        if ( [] === $columns ) {
            return;
        }

        echo '<section class="' . esc_attr( self::CLASS_NAME ) . '__addresses">';

        foreach ( $columns as $title => $address ) {
            echo '<div><h2>' . esc_html( $title ) . '</h2><address>' . wp_kses_post( $address ) . '</address></div>';
        }

        echo '</section>';
    }

    private static function print_customer_note( \WC_Order $order ): void {
        $note = trim( $order->get_customer_note() );

        if ( '' === $note ) {
            return;
        }

        echo '<section class="' . esc_attr( self::CLASS_NAME ) . '__note"><h2>' . esc_html__( 'Note', 'yaycheckout' ) . '</h2>';
        echo wp_kses_post( wpautop( wptexturize( esc_html( $note ) ) ) );
        echo '</section>';
    }
}
