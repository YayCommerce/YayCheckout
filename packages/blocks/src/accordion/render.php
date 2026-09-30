<?php
/**
 * Server render for yaycheckout/accordion: the step list.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks, already rendered by core.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

// Steps already took their numbers. Zero the counter before another accordion on this page renders.
if ( function_exists( 'yaycheckout_accordion_step_index' ) ) {
    yaycheckout_accordion_step_index( true );
}

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class'                 => 'yaycheckout-accordion',
        'data-required-message' => __( 'Please fill out this field.', 'yaycheckout' ),
        'data-select-message'   => __( 'Please choose an option.', 'yaycheckout' ),
    ]
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
