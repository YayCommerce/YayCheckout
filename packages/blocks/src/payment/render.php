<?php
/**
 * Prints the heading and the gateway list, which
 * CheckoutFragments refreshes on AJAX totals updates.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_label = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'label', __( 'Payment', 'yaycheckout' ) );

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper( [ 'class' => 'yaycheckout-payment' ], $attributes, \YayCheckout\Renderer\CheckoutFragments::payment_empty() );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <h3><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php \YayCheckout\Renderer\CheckoutFragments::print_payment_methods(); ?>
</div>
