<?php
/**
 * Server render for yaycheckout/shipping-methods.
 *
 * Storefront prints classic shipping radios. The editor keeps a placeholder.
 * AJAX totals replace .yaycheckout-shipping-methods__rates; see CheckoutFragments.
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
    : __( 'Shipping methods', 'yaycheckout' );

$yaycheckout_wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-shipping-methods' ] );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <h3><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php \YayCheckout\Renderer\CheckoutFragments::print_shipping_rates(); ?>
</div>
