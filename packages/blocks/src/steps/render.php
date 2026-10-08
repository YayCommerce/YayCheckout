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

/*
 * Block spacing. Steps has no layout support, because core prints no layout gap on classic
 * themes, so its blockGap is printed here the way core's layout.php prints it: the block's
 * own value, else the Global Styles value, plus each viewport value (WP 7.1+) in its media query.
 */
$yaycheckout_gap_css = static function ( $gap ) {
    if ( ! is_string( $gap ) || '' === $gap ) {
        return null;
    }

    if ( str_contains( $gap, 'var:preset|spacing|' ) ) {
        return 'var(--wp--preset--spacing--' . _wp_to_kebab_case( substr( $gap, strrpos( $gap, '|' ) + 1 ) ) . ')';
    }

    return $gap;
};

$yaycheckout_style     = $attributes['style'] ?? [];
$yaycheckout_gap_class = wp_unique_id( 'yaycheckout-steps-gap-' );
$yaycheckout_gap_rule  = static function ( $gap, $rules_group = null ) use ( $yaycheckout_gap_class ) {
    return array_filter(
        [
            // Two classes, to beat the stylesheet's .yaycheckout-steps default.
            'selector'     => '.yaycheckout-steps.' . $yaycheckout_gap_class,
            'declarations' => [ 'gap' => $gap ],
            'rules_group'  => $rules_group,
        ]
    );
};

$yaycheckout_gap       = $yaycheckout_gap_css( wp_sanitize_block_gap_value( $yaycheckout_style['spacing']['blockGap'] ?? null ) )
    ?? $yaycheckout_gap_css( wp_get_global_styles( [ 'spacing', 'blockGap' ], [ 'block_name' => 'yaycheckout/steps' ] ) );
$yaycheckout_gap_rules = null !== $yaycheckout_gap ? [ $yaycheckout_gap_rule( $yaycheckout_gap ) ] : [];

if ( method_exists( 'WP_Theme_JSON', 'get_viewport_media_queries' ) ) {
    foreach ( WP_Theme_JSON::get_viewport_media_queries( wp_get_global_settings( [ 'viewport' ] ) ) as $yaycheckout_viewport => $yaycheckout_media_query ) {
        $yaycheckout_viewport_gap = $yaycheckout_gap_css( wp_sanitize_block_gap_value( $yaycheckout_style[ $yaycheckout_viewport ]['spacing']['blockGap'] ?? null ) );

        if ( null !== $yaycheckout_viewport_gap ) {
            $yaycheckout_gap_rules[] = $yaycheckout_gap_rule( $yaycheckout_viewport_gap, $yaycheckout_media_query );
        }
    }
}

if ( $yaycheckout_gap_rules ) {
    wp_style_engine_get_stylesheet_from_css_rules(
        $yaycheckout_gap_rules,
        [
            'context'  => 'block-supports',
            'prettify' => false,
        ]
    );

    $yaycheckout_wrapper_attrs['class'] .= ' ' . $yaycheckout_gap_class;
}

$yaycheckout_wrapper = get_block_wrapper_attributes( $yaycheckout_wrapper_attrs );

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
</div>
