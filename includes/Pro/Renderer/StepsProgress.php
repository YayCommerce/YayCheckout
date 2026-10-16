<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Renderer\CheckoutFragments;
use YayCheckout\Renderer\LayoutSteps;

defined( 'ABSPATH' ) || exit;

/**
 * The Steps Progress block's markup. Steps Progress may sit inside or outside Checkout, so its titles
 * come from LayoutSteps, the served layout's first Steps block, not from block context.
 */
class StepsProgress {

    /** Mirrors steps-progress/block.json "variant" enum; the first is the default. */
    private const VARIANTS = [ 'circles', 'dots', 'segments', 'bar', 'text' ];

    /** Colour attribute => the variable style.css reads for it. */
    private const COLOR_VARS = [
        'fillColor'         => '--yaycheckout-steps-progress-fill',
        'trackColor'        => '--yaycheckout-steps-progress-track',
        'upcomingTextColor' => '--yaycheckout-steps-progress-upcoming-text',
    ];

    /**
     * Style of the block being rendered; the default when the attribute is missing or unknown.
     *
     * @param array $attributes Block attributes.
     */
    private static function variant( array $attributes ): string {
        $variant = $attributes['variant'] ?? '';

        return in_array( $variant, self::VARIANTS, true ) ? $variant : self::VARIANTS[0];
    }

    /**
     * Progress markup for the block being rendered. Empty when the layout has no steps.
     *
     * @param array $attributes Block attributes.
     */
    public static function markup( array $attributes = [] ): string {
        $titles = LayoutSteps::layout_titles();

        if ( [] === $titles ) {
            return '';
        }

        $variant = self::variant( $attributes );
        // Steps are buttons, except in bar and text, which show a summary and hide the list.
        $has_summary = 'bar' === $variant || 'text' === $variant;
        $tag         = $has_summary ? 'span' : 'button';
        $items       = '';

        foreach ( $titles as $position => $label ) {
            $number = (int) $position + 1;
            $first  = 1 === $number;
            $items .= '<li class="yaycheckout-steps-progress__item" data-state="' . ( $first ? 'current' : 'locked' ) . '" data-step="' . esc_attr( (string) $number ) . '"' . ( $first ? ' aria-current="step"' : '' ) . '>';
            $items .= '<' . $tag . ' class="yaycheckout-steps-progress__jump"' . ( $has_summary ? '' : ' type="button" disabled' ) . '>';
            $items .= '<span class="yaycheckout-steps-progress__marker">' . esc_html( (string) $number ) . '</span>';
            // LayoutSteps::step_title_html() filtered it with wp_kses.
            $items .= '<span class="yaycheckout-steps-progress__title">' . $label . '</span>';
            $items .= '</' . $tag . '></li>';
        }

        $args    = [ 'class' => 'yaycheckout-steps-progress is-variant-' . $variant ];
        $style   = self::color_style( $attributes );
        $summary = '';

        if ( $has_summary ) {
            $total = count( $titles );
            // Only the bar can leave the count out. Layouts saved before showCount existed lack the key and keep it.
            $with_count = 'bar' !== $variant || false !== ( $attributes['showCount'] ?? true );
            $summary    = self::summary( $total, 'bar' === $variant, $with_count );

            if ( 'bar' === $variant ) {
                $style .= '--yaycheckout-steps-progress-value:' . number_format( 1 / $total, 3, '.', '' ) . ';';
            }
        }

        if ( '' !== $style ) {
            $args['style'] = $style;
        }

        $wrapper = get_block_wrapper_attributes( $args );

        return '<div ' . $wrapper . '>' . $summary . '<ol class="yaycheckout-steps-progress__list">' . $items . '</ol></div>';
    }

    /**
     * The colour variables for each colour attribute set, as declarations ending in `;`; empty when none
     * is. Mirrors progressStyle() in steps-progress/edit.tsx.
     *
     * @param array $attributes Block attributes.
     */
    private static function color_style( array $attributes ): string {
        $style = '';

        foreach ( self::COLOR_VARS as $key => $var ) {
            $value = CheckoutFragments::css_color( $attributes[ $key ] ?? null );

            if ( '' !== $value ) {
                $style .= $var . ':' . $value . ';';
            }
        }

        return $style;
    }

    /**
     * "Step 1 of N", with the track for the bar style, which may leave the count out. view.ts rewrites the current span and the fill.
     */
    private static function summary( int $total, bool $with_track, bool $with_count ): string {
        $count = '';

        if ( $with_count ) {
            /* translators: 1: number of the open step, 2: number of steps. */
            $count = '<p class="yaycheckout-steps-progress__count">' . sprintf( esc_html__( 'Step %1$s of %2$s', 'yaycheckout' ), '<span class="yaycheckout-steps-progress__current">1</span>', (string) $total ) . '</p>';
        }

        $track = $with_track ? '<div class="yaycheckout-steps-progress__track"><span class="yaycheckout-steps-progress__fill"></span></div>' : '';

        return '<div class="yaycheckout-steps-progress__summary">' . $track . $count . '</div>';
    }
}
