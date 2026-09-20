<?php
/**
 * Server render for yaycheckout/payment.
 *
 * Storefront prints gateway radios. Place order is a separate block.
 * AJAX totals replace .woocommerce-checkout-payment; see CheckoutFragments.
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
    : __( 'Payment', 'yaycheckout' );

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'yaycheckout-payment' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <h3><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php \YayCheckout\Renderer\CheckoutFragments::print_payment_methods(); ?>
</div>
