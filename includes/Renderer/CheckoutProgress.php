<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Step list for the Progress block.
 *
 * Child templates have not run when Checkout starts, so the step counter is
 * still zero. The list travels on Checkout context instead.
 */
class CheckoutProgress {

    use SingletonTrait;

    /** Progress block.json usesContext lists this same key. */
    public const STEP_TITLES = 'yaycheckout/stepTitles';

    private const STEPS = 'yaycheckout/steps';
    private const STEP  = 'yaycheckout/step';

    protected function __construct() {
        add_filter( 'render_block_context', [ $this, 'provide_step_titles' ], 10, 2 );
    }

    /**
     * Step titles for blocks inside this Checkout.
     *
     * An empty first Steps block stays an empty list.
     *
     * @param array $context      Context for the block about to render.
     * @param array $parsed_block Parsed block.
     * @return array
     */
    public function provide_step_titles( $context, $parsed_block ) {
        if ( ! is_array( $context ) ) {
            $context = [];
        }

        if ( ! is_array( $parsed_block ) || 'yaycheckout/checkout' !== ( $parsed_block['blockName'] ?? '' ) ) {
            return $context;
        }

        $inner = is_array( $parsed_block['innerBlocks'] ?? null ) ? $parsed_block['innerBlocks'] : [];

        $context[ self::STEP_TITLES ] = self::titles_in( $inner );

        return $context;
    }

    /**
     * Label stored on a step, or "Step" when the merchant left it empty.
     */
    public static function step_title( string $raw ): string {
        return '' !== $raw ? $raw : __( 'Step', 'yaycheckout' );
    }

    /**
     * Progress markup for this block. Empty when this Checkout has no steps.
     */
    public static function markup( \WP_Block $block ): string {
        $titles = $block->context[ self::STEP_TITLES ] ?? [];

        if ( ! is_array( $titles ) || [] === $titles ) {
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
     * Titles of the first Steps block's Step children, in order; empty without one.
     *
     * @param array[] $blocks Checkout's inner blocks.
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
                    $titles[] = self::step_title( LayoutMarkupReader::text( is_array( $child['attrs'] ?? null ) ? $child['attrs'] : [], 'title' ) );
                }
            }

            return $titles;
        }

        return [];
    }
}
