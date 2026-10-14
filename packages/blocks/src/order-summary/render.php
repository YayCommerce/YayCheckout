<?php
/**
 * Prints the heading, then the Items, Order Summary Breakdown and Order Summary Total blocks with the merchant's blocks around them. A summary
 * split into a Summary Header and Summary Details prints no heading: the header holds the merchant's own,
 * and the wrapper says whether the details collapse and start open.
 * A layout saved before Order Summary held blocks has none, and gets WooCommerce's review-order
 * table as before. Without a frontend cart (admin, REST, CLI) that table is left out: the template
 * dereferences WC()->cart.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'label', \YayCheckout\Services\OrderSummaryCollapse::default_label() );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

$yaycheckout_new_shape = [] !== array_intersect(
    array_column( $block->parsed_block['innerBlocks'] ?? [], 'blockName' ),
    [ \YayCheckout\Services\OrderSummaryCollapse::HEADER, \YayCheckout\Services\OrderSummaryCollapse::DETAILS ]
);

// Block spacing.
$yaycheckout_class = 'yaycheckout-order-summary' . \YayCheckout\Renderer\BlockGap::wrapper_class( $attributes, 'yaycheckout/order-summary', 'yaycheckout-order-summary' );

if ( $yaycheckout_new_shape ) {
    $yaycheckout_collapse = \YayCheckout\Services\OrderSummaryCollapse::of_layout( \YayCheckout\Renderer\CheckoutPage::get_instance()->layout() );

    if ( $yaycheckout_collapse['collapsible'] ) {
        $yaycheckout_class .= $yaycheckout_collapse['open'] ? ' is-collapsible is-open' : ' is-collapsible';
    }
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
<?php if ( $yaycheckout_new_shape ) : ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
<?php else : ?>
    <h3 id="order_review_heading"><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php if ( ! empty( $block->parsed_block['innerBlocks'] ) ) : ?>
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
    <?php elseif ( $yaycheckout_has_cart && function_exists( 'woocommerce_order_review' ) ) : ?>
        <div id="order_review" class="woocommerce-checkout-review-order">
            <?php woocommerce_order_review(); ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
