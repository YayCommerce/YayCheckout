<?php
/**
 * Prints the heading, then the Items and Totals blocks with the merchant's blocks around them.
 * A layout saved before Order Summary held blocks has none, and gets WooCommerce's review-order
 * table as before. Without a frontend cart (admin, REST, CLI) that table is left out: the template
 * dereferences WC()->cart.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
    ? $attributes['label']
    : __( 'Your order', 'yaycheckout' );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

// Block spacing. Two classes, to beat the stylesheet's default.
$yaycheckout_class     = 'yaycheckout-order-summary';
$yaycheckout_gap_class = wp_unique_id( 'yaycheckout-order-summary-gap-' );

if ( \YayCheckout\Renderer\BlockGap::add_rules( $attributes['style'] ?? [], 'yaycheckout/order-summary', '.yaycheckout-order-summary.' . $yaycheckout_gap_class ) ) {
    $yaycheckout_class .= ' ' . $yaycheckout_gap_class;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <h3 id="order_review_heading"><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php if ( ! empty( $block->parsed_block['innerBlocks'] ) ) : ?>
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
    <?php elseif ( $yaycheckout_has_cart && function_exists( 'woocommerce_order_review' ) ) : ?>
        <div id="order_review" class="woocommerce-checkout-review-order">
            <?php woocommerce_order_review(); ?>
        </div>
    <?php endif; ?>
</div>
