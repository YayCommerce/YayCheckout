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

// Mirrors steps/block.json "layout" enum; anything else is the default, accordion.
$yaycheckout_layout = in_array( $attributes['layout'] ?? '', [ 'accordion', 'one-at-a-time', 'timeline' ], true ) ? $attributes['layout'] : 'accordion';

$yaycheckout_wrapper_attrs = [
    'class'                 => 'yaycheckout-steps',
    'data-layout'           => $yaycheckout_layout,
    'data-required-message' => __( 'Please fill out this field.', 'yaycheckout' ),
    'data-select-message'   => __( 'Please choose an option.', 'yaycheckout' ),
];

// A layout saved before the setting has no value: headings show.
if ( false === ( $attributes['showHeadings'] ?? true ) ) {
    $yaycheckout_wrapper_attrs['data-headings'] = 'hidden';
}

// Block spacing.
$yaycheckout_wrapper_attrs['class'] .= \YayCheckout\Renderer\BlockGap::wrapper_class( $attributes, 'yaycheckout/steps', 'yaycheckout-steps' );

// Title and Summary styles.
$yaycheckout_text_class = \YayCheckout\Pro\Renderer\StepsTextStyles::wrapper_class( $attributes, 'yaycheckout/steps' );
if ( '' !== $yaycheckout_text_class ) {
    $yaycheckout_wrapper_attrs['class'] .= ' ' . $yaycheckout_text_class;
}

$yaycheckout_wrapper = get_block_wrapper_attributes( $yaycheckout_wrapper_attrs );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
