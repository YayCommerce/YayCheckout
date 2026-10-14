<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * The heading level and the Title and Description styles of Billing, Shipping, Shipping Methods and
 * Payment. The styles print in core's block-supports stylesheet as one rule per part, under a unique
 * wrapper class; Payment's and Shipping Methods' fragments replace only their list, so the class stays.
 * packages/blocks/src/shared/section-heading.tsx mirrors this for the editor.
 */
final class SectionTextStyles {

    /** Part key under the block's style.yaycheckout => the class of the element it styles. */
    const PARTS = [
        'title'       => 'yaycheckout-section-title',
        'description' => 'yaycheckout-section-description',
    ];

    const DEFAULT_LEVEL = 3;

    /**
     * The heading tag for the block's headingLevel: h1 to h6, h3 when missing or not a number.
     *
     * @param array $attributes The block's attributes.
     */
    public static function heading_tag( array $attributes ): string {
        $level = $attributes['headingLevel'] ?? null;
        $level = is_numeric( $level ) ? max( 1, min( 6, (int) $level ) ) : self::DEFAULT_LEVEL;

        return 'h' . $level;
    }

    /**
     * Queues the rules for one block and names the class they need.
     *
     * @param array  $attributes The block's attributes.
     * @param string $block_name Block name; its wrapper class raises the rules above checkout-base.css and the design's heading rule.
     * @return string The unique class for the wrapper; '' when nothing was queued.
     */
    public static function wrapper_class( array $attributes, string $block_name ): string {
        $parts = $attributes['style']['yaycheckout'] ?? null;

        if ( ! is_array( $parts ) ) {
            return '';
        }

        $class  = wp_unique_id( 'yaycheckout-section-text-' );
        $queued = false;

        foreach ( self::PARTS as $part => $element_class ) {
            if ( ! is_array( $parts[ $part ] ?? null ) ) {
                continue;
            }

            $styles = wp_style_engine_get_styles(
                array_intersect_key( $parts[ $part ], SummaryPartStyles::KEYS ),
                [
                    'selector' => '.yaycheckout-checkout .' . $class . '.' . wp_get_block_default_classname( $block_name ) . ' > .' . $element_class,
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
