<?php
/**
 * Prints WooCommerce's order details table on a taken-over thank-you page: line items, totals,
 * downloads, customer addresses and whatever plugins attach to that template's hooks. Same output as
 * woocommerce_order_details_table on woocommerce_thankyou. Wrapper holds block style supports.
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

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-details' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php woocommerce_order_details_table( $yaycheckout_order->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output. ?>
</div>
