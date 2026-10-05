<?php
namespace YayCheckout\Renderer;

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
        $found = self::titles_in( $inner );

        $context[ self::STEP_TITLES ] = $found ?? [];

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
            $number  = (int) $position + 1;
            $state   = 1 === $number ? 'current' : 'locked';
            $current = 'current' === $state ? ' aria-current="step"' : '';
            $items  .= '<li class="yaycheckout-progress__item" data-state="' . esc_attr( $state ) . '" data-step="' . esc_attr( (string) $number ) . '"' . $current . '>';
            $items  .= '<button type="button" class="yaycheckout-progress__jump" disabled>';
            $items  .= '<span class="yaycheckout-progress__marker">' . esc_html( (string) $number ) . '</span>';
            $items  .= '<span class="yaycheckout-progress__title">' . esc_html( (string) $label ) . '</span>';
            $items  .= '</button></li>';
        }

        $wrapper = get_block_wrapper_attributes( [ 'class' => 'yaycheckout-progress' ] );

        return '<ol ' . $wrapper . '>' . $items . '</ol>';
    }

    /**
     * @param array[] $blocks
     * @return string[]|null Null when this branch has no Steps block.
     */
    private static function titles_in( array $blocks ): ?array {
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            if ( 'yaycheckout/steps' === ( $block['blockName'] ?? '' ) ) {
                $inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];

                return self::child_titles( $inner );
            }

            $inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];
            $found = self::titles_in( $inner );

            if ( null !== $found ) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array[] $blocks Direct children of a Steps block.
     * @return string[]
     */
    private static function child_titles( array $blocks ): array {
        $titles = [];

        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) || 'yaycheckout/step' !== ( $block['blockName'] ?? '' ) ) {
                continue;
            }

            $raw      = $block['attrs']['title'] ?? '';
            $titles[] = self::step_title( is_string( $raw ) ? $raw : '' );
        }

        return $titles;
    }
}
