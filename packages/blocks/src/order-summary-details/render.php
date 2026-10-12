<?php
/**
 * Prints the details' blocks: Items, Totals and the merchant's own. The wrapper carries none of the
 * classes CheckoutFragments replaces, so an order review refresh leaves it and its state alone.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Block spacing.
$yaycheckout_class = 'yaycheckout-order-summary-details' . \YayCheckout\Renderer\BlockGap::wrapper_class( $attributes, 'yaycheckout/order-summary-details', 'yaycheckout-order-summary-details' );

?>
<div id="yaycheckout-order-summary-details" <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
