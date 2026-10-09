<?php
/**
 * Server render for yaycheckout/steps: the step list.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks, already rendered by core.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

// Steps already took their numbers. Zero the counter before another Steps block on this page renders.
if ( function_exists( 'yaycheckout_step_index' ) ) {
    yaycheckout_step_index( true );
}

$yaycheckout_layout = isset( $attributes['layout'] ) && 'single' === $attributes['layout'] ? 'single' : 'list';

$yaycheckout_wrapper_attrs = [
    'class'                 => 'yaycheckout-steps',
    'data-layout'           => $yaycheckout_layout,
    'data-required-message' => __( 'Please fill out this field.', 'yaycheckout' ),
    'data-select-message'   => __( 'Please choose an option.', 'yaycheckout' ),
];

// Block spacing. Two classes, to beat the stylesheet's .yaycheckout-steps default.
$yaycheckout_gap_class = wp_unique_id( 'yaycheckout-steps-gap-' );

if ( \YayCheckout\Renderer\BlockGap::add_rules( $attributes['style'] ?? [], 'yaycheckout/steps', '.yaycheckout-steps.' . $yaycheckout_gap_class ) ) {
    $yaycheckout_wrapper_attrs['class'] .= ' ' . $yaycheckout_gap_class;
}

$yaycheckout_wrapper = get_block_wrapper_attributes( $yaycheckout_wrapper_attrs );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
