<?php
/**
 * Prints the Billing section: an optional heading and its inner Field blocks.
 * woocommerce-billing-fields is the wrapper country-select.js searches for
 * #billing_state; without it the state box never repopulates.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks markup, already rendered by core.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_title      = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : __( 'Billing', 'yaycheckout' );
$yaycheckout_show_title = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];

// Block spacing. Two classes, to beat the stylesheet's default.
$yaycheckout_class     = 'yaycheckout-billing woocommerce-billing-fields';
$yaycheckout_gap_class = wp_unique_id( 'yaycheckout-billing-gap-' );

if ( \YayCheckout\Renderer\BlockGap::add_rules( $attributes['style'] ?? [], 'yaycheckout/billing', '.yaycheckout-billing.' . $yaycheckout_gap_class ) ) {
    $yaycheckout_class .= ' ' . $yaycheckout_gap_class;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => $yaycheckout_class ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php if ( $yaycheckout_show_title ) : ?>
        <h3><?php echo esc_html( $yaycheckout_title ); ?></h3>
    <?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
