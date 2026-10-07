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

    /** @var string[]|null Memoised layout_titles(); null until first read. */
    private static $titles = null;

    /**
     * Label stored on a step, or "Step" when the merchant left it empty.
     */
    public static function step_title( string $raw ): string {
        return '' !== $raw ? $raw : __( 'Step', 'yaycheckout' );
    }

    /**
     * Progress markup for the block being rendered. Empty when the layout has no steps.
     */
    public static function markup(): string {
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

        $wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-progress' ] );

        return '<ol ' . $wrapper . '>' . $items . '</ol>';
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
