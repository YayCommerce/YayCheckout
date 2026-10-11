<?php
/**
 * Prints the heading and the shipping rates, which
 * CheckoutFragments refreshes on AJAX totals updates.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'label', __( 'Shipping methods', 'yaycheckout' ) );

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper( [ 'class' => 'yaycheckout-shipping-methods' ], $attributes, \YayCheckout\Renderer\CheckoutFragments::shipping_empty() );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <h3><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php \YayCheckout\Renderer\CheckoutFragments::print_shipping_rates(); ?>
</div>
