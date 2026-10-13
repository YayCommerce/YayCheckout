<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Step list for the Steps Progress block.
 *
 * Steps Progress may sit inside or outside Checkout, so its titles come from the
 * rendered layout's first Steps block, not from block context. A layout holds
 * one Checkout, so that is also the first Steps block inside Checkout.
 */
class CheckoutStepsProgress {

    private const STEPS = 'yaycheckout/steps';
    private const STEP  = 'yaycheckout/step';

    /** Mirrors steps-progress/block.json "variant" enum; the first is the default. */
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
     * A Step block's title as HTML safe to echo, with the display-text toolbar's formatting and live values,
     * or "Step" while it holds no text. The step's header and Steps Progress print it alike.
     *
     * @param array $attrs The Step block's attributes.
     */
    public static function step_title_html( array $attrs ): string {
        return LayoutMarkupReader::rich_text( $attrs, 'title', self::step_title( '' ) );
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
            // step_title_html() filtered it with wp_kses.
            $items .= '<span class="yaycheckout-steps-progress__title">' . $label . '</span>';
            $items .= '</' . $tag . '></li>';
        }

        $args    = [ 'class' => 'yaycheckout-steps-progress is-variant-' . $variant ];
        $summary = '';

        if ( $has_summary ) {
            $total = count( $titles );
            // Only the bar can leave the count out. Layouts saved before showCount existed lack the key and keep it.
            $with_count = 'bar' !== $variant || false !== ( $attributes['showCount'] ?? true );
            $summary    = self::summary( $total, 'bar' === $variant, $with_count );

            if ( 'bar' === $variant ) {
                $args['style'] = '--yaycheckout-steps-progress-value:' . number_format( 1 / $total, 3, '.', '' ) . ';';
            }
        }

        $wrapper = get_block_wrapper_attributes( $args );

        return '<div ' . $wrapper . '>' . $summary . '<ol class="yaycheckout-steps-progress__list">' . $items . '</ol></div>';
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

    /**
     * Titles of the rendered layout's first Steps block, as HTML safe to echo; empty without a layout or a
     * Steps block. Go to Step reads it too, to count the steps.
     *
     * @return string[]
     */
    public static function layout_titles(): array {
        if ( null === self::$titles ) {
            $layout       = CheckoutPage::get_instance()->layout();
            self::$titles = null !== $layout ? self::titles_in( $layout->content ) : [];
        }

        return self::$titles;
    }

    /**
     * Titles of the first Steps block's Step children, in order, as step_title_html() prints them; empty without one.
     *
     * @return string[]
     */
    private static function titles_in( string $markup ): array {
        $block  = LayoutMarkupReader::first_block( $markup, self::STEPS );
        $titles = [];

        foreach ( null !== $block ? $block['innerBlocks'] : [] as $child ) {
            if ( self::STEP === ( $child['blockName'] ?? '' ) ) {
                $titles[] = self::step_title_html( is_array( $child['attrs'] ?? null ) ? $child['attrs'] : [] );
            }
        }

        return $titles;
    }
}
