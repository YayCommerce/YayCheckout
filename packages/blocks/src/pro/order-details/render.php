<?php
/**
 * Prints WooCommerce's order details table on a taken-over thank-you page: line items, totals,
 * downloads and plugin hooks on that template — without the billing/shipping section, which the
 * Order Addresses block prints via order-details-customer.php. Optional title / column / totals-row
 * labels from the block override WooCommerce's gettext while the table prints. Show title off strips
 * the WC heading. Wrapper holds block style supports.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_order = \YayCheckout\Pro\Renderer\ThankYouPage::get_instance()->order();

if ( null === $yaycheckout_order ) {
    return;
}

$yaycheckout_show_title           = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];
$yaycheckout_title                = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'title' );
$yaycheckout_product_label        = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'productLabel' );
$yaycheckout_total_label          = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'totalLabel' );
$yaycheckout_subtotal_label       = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'subtotalLabel' );
$yaycheckout_shipping_label       = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'shippingLabel' );
$yaycheckout_payment_method_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'paymentMethodLabel' );
$yaycheckout_order_total_label    = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'orderTotalLabel' );

$yaycheckout_overrides = [
    'Product'         => $yaycheckout_product_label,
    'Total'           => $yaycheckout_total_label,
    'Subtotal:'       => $yaycheckout_subtotal_label,
    'Shipping:'       => $yaycheckout_shipping_label,
    'Payment method:' => $yaycheckout_payment_method_label,
    'Total:'          => $yaycheckout_order_total_label,
];

if ( $yaycheckout_show_title ) {
    $yaycheckout_overrides['Order details'] = $yaycheckout_title;
}

ob_start();
\YayCheckout\Pro\Renderer\ThankYouPage::with_woocommerce_label_overrides(
    $yaycheckout_overrides,
    static function () use ( $yaycheckout_order ): void {
        woocommerce_order_details_table( $yaycheckout_order->get_id() );
    }
);
$yaycheckout_table = (string) ob_get_clean();

// woocommerce_order_details_table always appends order-details-customer.php; Order Addresses owns that.
$yaycheckout_table = \YayCheckout\Pro\Renderer\ThankYouPage::without_customer_details( $yaycheckout_table );

if ( ! $yaycheckout_show_title ) {
    $yaycheckout_table = \YayCheckout\Pro\Renderer\ThankYouPage::without_order_details_title( $yaycheckout_table );
}

if ( '' === trim( $yaycheckout_table ) ) {
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-details' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output, customer section removed. ?>
</div>
