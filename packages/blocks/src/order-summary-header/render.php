<?php
/**
 * Prints the header's blocks in core's flex row. While the summary is collapsible, an empty button
 * covers the row first, so a click anywhere on it toggles the details; the header's own controls sit
 * above it (order-summary/style.css) and keep their action.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_collapse = \YayCheckout\Services\OrderSummaryCollapse::of_layout( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );

// Order Summary's Label names the button, since the header's own text may be a total or a button.
$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::text( [ 'label' => $block->context['yaycheckout/orderSummaryLabel'] ?? null ], 'label', \YayCheckout\Services\OrderSummaryCollapse::default_label() );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-header' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_collapse['collapsible'] ) : ?>
        <button type="button" class="yaycheckout-order-summary__header-button" aria-expanded="<?php echo $yaycheckout_collapse['open'] ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( \YayCheckout\Services\OrderSummaryCollapse::DETAILS_ID ); ?>" aria-label="<?php echo esc_attr( $yaycheckout_label ); ?>"></button>
    <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
