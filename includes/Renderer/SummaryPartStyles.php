<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the Label and Value styles of Order Summary Breakdown and Order Summary Total in core's
 * block-supports stylesheet: one rule for the label cells, one for the amount cells, under a unique
 * wrapper class. checkout.js replaces only the table on a totals refresh, so the class stays.
 * summaryPartCSS() in packages/blocks/src/shared/summary-part-styles.tsx mirrors this for the editor.
 */
final class SummaryPartStyles {

    /** Part key under the block's style.yaycheckout => the cell it styles. */
    const PARTS = [
        'label' => 'th',
        'value' => 'td',
    ];

    /** The keys of a part that the rules print. */
    const KEYS = [
        'typography' => true,
        'color'      => true,
    ];

    /**
     * Queues the rules for one block and names the class they need.
     *
     * @param array  $attributes  The block's attributes.
     * @param string $block_name  Block name; its wrapper class raises the rules above the blocks' own cell rules.
     * @param string $table_class The class of the table inside the wrapper.
     * @return string The unique class for the wrapper; '' when nothing was queued.
     */
    public static function wrapper_class( array $attributes, string $block_name, string $table_class ): string {
        $parts = $attributes['style']['yaycheckout'] ?? null;

        if ( ! is_array( $parts ) ) {
            return '';
        }

        $class  = wp_unique_id( 'yaycheckout-summary-parts-' );
        $queued = false;

        foreach ( self::PARTS as $part => $cell ) {
            if ( ! is_array( $parts[ $part ] ?? null ) ) {
                continue;
            }

            $styles = wp_style_engine_get_styles(
                array_intersect_key( $parts[ $part ], self::KEYS ),
                [
                    'selector' => '.yaycheckout-order-summary .' . $class . '.' . wp_get_block_default_classname( $block_name ) . ' table.' . $table_class . ' tr > ' . $cell,
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
