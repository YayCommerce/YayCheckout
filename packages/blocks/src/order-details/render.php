<?php
/**
 * Prints WooCommerce's order details table on a taken-over thank-you page: line items, totals,
 * downloads and plugin hooks on that template — without the billing/shipping section, which the
 * Order Addresses block prints via order-details-customer.php. Optional title / column labels from
 * the block override WooCommerce's gettext while the table prints. Wrapper holds block style supports.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_order = \YayCheckout\Renderer\ThankYouPage::get_instance()->order();

if ( null === $yaycheckout_order ) {
    return;
}

$yaycheckout_title         = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'title' );
$yaycheckout_product_label = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'productLabel' );
$yaycheckout_total_label   = \YayCheckout\Services\LayoutMarkupReader::plain_text( $attributes, 'totalLabel' );

ob_start();
\YayCheckout\Renderer\ThankYouPage::with_woocommerce_label_overrides(
    [
        'Order details' => $yaycheckout_title,
        'Product'       => $yaycheckout_product_label,
        'Total'         => $yaycheckout_total_label,
    ],
    static function () use ( $yaycheckout_order ): void {
        woocommerce_order_details_table( $yaycheckout_order->get_id() );
    }
);
$yaycheckout_table = (string) ob_get_clean();

// woocommerce_order_details_table always appends order-details-customer.php; Order Addresses owns that.
$yaycheckout_table = \YayCheckout\Renderer\ThankYouPage::without_customer_details( $yaycheckout_table );

if ( '' === trim( $yaycheckout_table ) ) {
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-details' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output, customer section removed. ?>
</div>
