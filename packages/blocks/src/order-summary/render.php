<?php
/**
 * Server render for yaycheckout/order-summary.
 *
 * Storefront prints the classic review-order table (AJAX fragment target).
 *
 * Without a frontend cart (admin, REST, CLI) the heading stays and the table
 * does not: WooCommerce's review-order template dereferences WC()->cart.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
    ? $attributes['label']
    : __( 'Your order', 'yaycheckout' );

$yaycheckout_has_cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart;

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-order-summary' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <h3 id="order_review_heading"><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php if ( $yaycheckout_has_cart && function_exists( 'woocommerce_order_review' ) ) : ?>
        <div id="order_review" class="woocommerce-checkout-review-order">
            <?php woocommerce_order_review(); ?>
        </div>
    <?php endif; ?>
</div>
