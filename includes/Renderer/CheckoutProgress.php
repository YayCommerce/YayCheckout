<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Step list for the Progress block.
 *
 * Progress may sit inside or outside Checkout, so its titles come from the
 * rendered layout's first Steps block, not from block context. A layout holds
 * one Checkout, so that is also the first Steps block inside Checkout.
 */
class CheckoutProgress {

    private const STEPS = 'yaycheckout/steps';
    private const STEP  = 'yaycheckout/step';

    /** Mirrors progress/block.json "variant" enum; the first is the default. */
    private const VARIANTS = [ 'circles', 'dots', 'segments', 'bar', 'text' ];

    /** @var string[]|null Memoised layout_titles(); null until first read. */
    private static $titles = null;

    /**
     * Label stored on a step, or "Step" when the merchant left it empty.
     */
    public static function step_title( string $raw ): string {
        return '' !== $raw ? $raw : __( 'Step', 'yaycheckout' );
    }

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
        $titles = self::layout_titles();

        if ( [] === $titles ) {
            return '';
        }

        $items = '';

        foreach ( $titles as $position => $label ) {
            $number = (int) $position + 1;
            $first  = 1 === $number;
            $items .= '<li class="yaycheckout-progress__item" data-state="' . ( $first ? 'current' : 'locked' ) . '" data-step="' . esc_attr( (string) $number ) . '"' . ( $first ? ' aria-current="step"' : '' ) . '>';
            $items .= '<button type="button" class="yaycheckout-progress__jump" disabled>';
            $items .= '<span class="yaycheckout-progress__marker">' . esc_html( (string) $number ) . '</span>';
            $items .= '<span class="yaycheckout-progress__title">' . esc_html( (string) $label ) . '</span>';
            $items .= '</button></li>';
        }

        $variant = self::variant( $attributes );
        $args    = [ 'class' => 'yaycheckout-progress is-variant-' . $variant ];
        $summary = '';

        if ( 'bar' === $variant || 'text' === $variant ) {
            $total   = count( $titles );
            $summary = self::summary( $total, 'bar' === $variant );

            if ( 'bar' === $variant ) {
                $args['style'] = '--yaycheckout-progress-value:' . round( 1 / $total, 3 ) . ';';
            }
        }

        $wrapper = get_block_wrapper_attributes( $args );

        return '<div ' . $wrapper . '>' . $summary . '<ol class="yaycheckout-progress__list">' . $items . '</ol></div>';
    }

    /**
     * "Step 1 of N", with the track for the bar style. view.ts rewrites the current span and the fill.
     */
    private static function summary( int $total, bool $with_track ): string {
        /* translators: 1: number of the open step, 2: number of steps. */
        $count = sprintf( esc_html__( 'Step %1$s of %2$s', 'yaycheckout' ), '<span class="yaycheckout-progress__current">1</span>', (string) $total );

        $track = $with_track ? '<div class="yaycheckout-progress__track"><span class="yaycheckout-progress__fill"></span></div>' : '';

        return '<div class="yaycheckout-progress__summary">' . $track . '<p class="yaycheckout-progress__count">' . $count . '</p></div>';
    }

    /**
     * Titles of the rendered layout's first Steps block; empty without a layout or a Steps block.
     *
     * @return string[]
     */
    private static function layout_titles(): array {
        if ( null === self::$titles ) {
            $layout       = CheckoutPage::get_instance()->layout();
            self::$titles = null !== $layout ? self::titles_in( parse_blocks( $layout->content ) ) : [];
        }

        return self::$titles;
    }

    /**
     * Titles of the first Steps block's Step children, in order; empty without one.
     *
     * @param array[] $blocks The layout's blocks.
     * @return string[]
     */
    private static function titles_in( array $blocks ): array {
        foreach ( LayoutMarkupReader::flatten( $blocks ) as $block ) {
            if ( self::STEPS !== $block['blockName'] ) {
                continue;
            }

            $titles = [];

            foreach ( $block['innerBlocks'] as $child ) {
                if ( self::STEP === ( $child['blockName'] ?? '' ) ) {
                    $titles[] = self::step_title( LayoutMarkupReader::plain_text( is_array( $child['attrs'] ?? null ) ? $child['attrs'] : [], 'title' ) );
                }
            }

            return $titles;
        }

        return [];
    }
}
