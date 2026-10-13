<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the Label and Value styles of Order Summary Breakdown and Order Summary Total in core's
 * block-supports stylesheet: one rule for the label cells, one for the amount cells, under a unique
 * wrapper class. checkout.js replaces only the table on a totals refresh, so the class stays.
 * Mirrors summaryPartCSS() in packages/blocks/src/shared/summary-part-styles.tsx.
 */
final class SummaryPartStyles {

    /** Part key under the block's style.yaycheckout => the cell it styles. */
    const PARTS = [
        'label' => 'th',
        'value' => 'td',
    ];

    /**
     * Queues the rules for one block and names the class they need.
     *
     * @param array  $attributes  The block's attributes.
     * @param string $block_class The block's wrapper class, which raises the rules above the blocks' own cell rules.
     * @param string $table_class The class of the table inside the wrapper.
     * @return string A space and the unique class, to append to the wrapper's class; '' when nothing was queued.
     */
    public static function wrapper_class( array $attributes, string $block_class, string $table_class ): string {
        $parts = $attributes['style']['yaycheckout'] ?? null;

        if ( ! is_array( $parts ) ) {
            return '';
        }

        $class  = wp_unique_id( 'yaycheckout-summary-parts-' );
        $queued = false;

        foreach ( self::PARTS as $part => $cell ) {
            if ( empty( $parts[ $part ] ) || ! is_array( $parts[ $part ] ) ) {
                continue;
            }

            $styles = wp_style_engine_get_styles(
                array_intersect_key( $parts[ $part ], array_flip( [ 'typography', 'color' ] ) ),
                [
                    'selector' => '.yaycheckout-order-summary .' . $class . '.' . $block_class . ' table.' . $table_class . ' tr > ' . $cell,
                    'context'  => 'block-supports',
                ]
            );

            if ( ! empty( $styles['declarations'] ) ) {
                $queued = true;
            }
        }

        return $queued ? ' ' . $class : '';
    }
}
