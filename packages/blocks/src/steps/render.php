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

// blockGap is skipped in block.json; print it as inline gap so core layout styles stay off.
$yaycheckout_block_gap = isset( $attributes['style']['spacing']['blockGap'] ) ? $attributes['style']['spacing']['blockGap'] : null;

if ( is_string( $yaycheckout_block_gap ) && '' !== $yaycheckout_block_gap ) {
    $yaycheckout_preset_prefix = 'var:preset|spacing|';

    if ( 0 === strpos( $yaycheckout_block_gap, $yaycheckout_preset_prefix ) ) {
        $yaycheckout_block_gap = 'var(--wp--preset--spacing--' . substr( $yaycheckout_block_gap, strlen( $yaycheckout_preset_prefix ) ) . ')';
    }

    $yaycheckout_gap_style = safecss_filter_attr( 'gap:' . $yaycheckout_block_gap );

    if ( is_string( $yaycheckout_gap_style ) && '' !== $yaycheckout_gap_style ) {
        $yaycheckout_wrapper_attrs['style'] = $yaycheckout_gap_style;
    }
}

$yaycheckout_wrapper = get_block_wrapper_attributes( $yaycheckout_wrapper_attrs );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
