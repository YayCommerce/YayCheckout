<?php
/**
 * Prints the details' blocks: Items, Totals and the merchant's own. The wrapper carries none of the
 * classes CheckoutFragments replaces, so an order review refresh leaves it and its state alone. It is
 * hidden on the server while it starts collapsed, so it never flashes open.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_collapse = \YayCheckout\Services\OrderSummaryCollapse::of_layout( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );

// Block spacing.
$yaycheckout_class = 'yaycheckout-order-summary-details' . \YayCheckout\Renderer\BlockGap::wrapper_class( $attributes, 'yaycheckout/order-summary-details', 'yaycheckout-order-summary-details' );

?>
<div id="<?php echo esc_attr( \YayCheckout\Services\OrderSummaryCollapse::DETAILS_ID ); ?>" <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?><?php echo $yaycheckout_collapse['open'] ? '' : ' hidden'; ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
