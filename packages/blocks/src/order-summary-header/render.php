<?php
/**
 * Prints the header's blocks in core's flex row. While the summary is collapsible, an empty button
 * covers the row first, so a click anywhere on it toggles the details; the header's own controls sit
 * above it (order-summary/style.css) and keep their action. A Toggle in the header is then the one
 * keyboard control, so the button leaves the tab order and the accessibility tree: a second focus
 * ring for the same action.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_collapse = \YayCheckout\Services\OrderSummaryCollapse::of_layout( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );

// Order Summary's Label names the button, since the header's own text may be a total or a button.
$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::text( [ 'label' => $block->context['yaycheckout/orderSummaryLabel'] ?? null ], 'label', \YayCheckout\Services\OrderSummaryCollapse::default_label() );

$yaycheckout_button_attrs = \YayCheckout\Services\OrderSummaryCollapse::has_toggle( $block->parsed_block['innerBlocks'] ?? [] )
    ? 'tabindex="-1" aria-hidden="true"'
    : sprintf(
        'aria-expanded="%s" aria-controls="%s" aria-label="%s"',
        $yaycheckout_collapse['open'] ? 'true' : 'false',
        esc_attr( \YayCheckout\Services\OrderSummaryCollapse::DETAILS_ID ),
        esc_attr( $yaycheckout_label )
    );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary-header' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_collapse['collapsible'] ) : ?>
        <button type="button" class="yaycheckout-order-summary__header-button" <?php echo $yaycheckout_button_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built escaped above. ?>></button>
    <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
