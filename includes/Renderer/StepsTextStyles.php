<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * The Title and Summary styles of the Steps block, applied to every step header in it. The styles print
 * in core's block-supports stylesheet as one rule per part, under a unique wrapper class.
 * packages/blocks/src/steps/edit.tsx mirrors this for the editor.
 */
final class StepsTextStyles {

    /** Part key under the block's style.yaycheckout => the class of the header element it styles. */
    const PARTS = [
        'title'   => 'yaycheckout-step__title',
        'summary' => 'yaycheckout-step__summary',
    ];

    /**
     * Queues the rules for one Steps block and names the class they need.
     *
     * @param array  $attributes The block's attributes.
     * @param string $block_name Block name; its wrapper class raises the rules above steps/style.css, the locked title's muting included.
     * @return string The unique class for the wrapper; '' when nothing was queued.
     */
    public static function wrapper_class( array $attributes, string $block_name ): string {
        $parts = $attributes['style']['yaycheckout'] ?? null;

        if ( ! is_array( $parts ) ) {
            return '';
        }

        $class  = wp_unique_id( 'yaycheckout-steps-text-' );
        $queued = false;

        foreach ( self::PARTS as $part => $element_class ) {
            if ( ! is_array( $parts[ $part ] ?? null ) ) {
                continue;
            }

            $styles = wp_style_engine_get_styles(
                array_intersect_key( $parts[ $part ], SummaryPartStyles::KEYS ),
                [
                    'selector' => '.yaycheckout-checkout .' . $class . '.' . wp_get_block_default_classname( $block_name ) . ' .yaycheckout-step__header > .' . $element_class,
                    'context'  => 'block-supports',
                ]
            );

            if ( ! empty( $styles['declarations'] ) ) {
                $queued = true;
            }
        }

        return $queued ? $class : '';
    }
}
