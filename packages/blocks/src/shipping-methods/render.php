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

$yaycheckout_label       = \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'label', __( 'Shipping methods', 'yaycheckout' ) );
$yaycheckout_show_title  = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];
$yaycheckout_description = trim( \YayCheckout\Services\LayoutMarkupReader::text( $attributes, 'description' ) );

$yaycheckout_wrapper_base = [
    'class'       => 'yaycheckout-shipping-methods',
    'data-layout' => \YayCheckout\Renderer\CheckoutFragments::shipping_layout( $attributes ),
];

$yaycheckout_style = \YayCheckout\Renderer\CheckoutFragments::shipping_methods_style_attribute( $attributes );
if ( '' !== $yaycheckout_style ) {
    $yaycheckout_wrapper_base['style'] = $yaycheckout_style;
}

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper(
    $yaycheckout_wrapper_base,
    $attributes,
    \YayCheckout\Renderer\CheckoutFragments::shipping_empty()
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_show_title ) : ?>
        <h3><?php echo esc_html( $yaycheckout_label ); ?></h3>
    <?php endif; ?>
    <?php if ( '' !== $yaycheckout_description ) : ?>
        <p class="yaycheckout-section-description"><?php echo esc_html( $yaycheckout_description ); ?></p>
    <?php endif; ?>
    <?php \YayCheckout\Renderer\CheckoutFragments::print_shipping_rates(); ?>
</div>
