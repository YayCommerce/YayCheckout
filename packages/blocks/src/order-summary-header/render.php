<?php
/**
 * Prints the header's blocks in core's flex row. While the summary is collapsible, order-summary/view.ts
 * toggles the details on a click anywhere on the row outside the header's own controls. A header with a
 * Summary Toggle leaves the keyboard to it; one without takes a tab stop itself, opened with Enter or Space.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_collapse = \YayCheckout\Services\OrderSummaryCollapse::of_layout( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );
$yaycheckout_extra    = [ 'class' => 'yaycheckout-order-summary-header' ];

if ( $yaycheckout_collapse['collapsible'] && ! \YayCheckout\Services\OrderSummaryCollapse::has_toggle( $block->parsed_block['innerBlocks'] ?? [] ) ) {
    $yaycheckout_extra['tabindex'] = '0';
}

?>
<div <?php echo get_block_wrapper_attributes( $yaycheckout_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
