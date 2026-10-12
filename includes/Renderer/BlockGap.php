<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Prints a block's Block spacing in core's block-supports stylesheet, as a `gap` rule, or as
 * child margins the way core's flow layout prints them.
 *
 * Our blocks have no layout support, because core prints no layout gap on classic themes, so
 * their blockGap is printed here the way core's layout.php prints it: the block's own value,
 * else the Global Styles value, plus each viewport value (WP 7.1+) in its media query.
 */
final class BlockGap {

    /**
     * Queues the gap rules for $selector.
     *
     * @param array  $style      The block's `style` attribute.
     * @param string $block_name Block name, for its Global Styles value.
     * @param string $selector   The element the gap applies to; the caller sets its specificity.
     * @param bool   $flow       True for margins between the element's children instead of `gap`. A child's own
     *                           Margin setting, printed inline, still wins.
     * @return bool False when nothing was queued.
     */
    public static function add_rules( array $style, string $block_name, string $selector, bool $flow = false ): bool {
        $gap   = self::css_value( wp_sanitize_block_gap_value( $style['spacing']['blockGap'] ?? null ) )
            ?? self::css_value( wp_get_global_styles( [ 'spacing', 'blockGap' ], [ 'block_name' => $block_name ] ) );
        $rules = null !== $gap ? self::rules( $selector, $gap, $flow ) : [];

        if ( method_exists( 'WP_Theme_JSON', 'get_viewport_media_queries' ) ) {
            foreach ( \WP_Theme_JSON::get_viewport_media_queries( wp_get_global_settings( [ 'viewport' ] ) ) as $viewport => $media_query ) {
                $viewport_gap = self::css_value( wp_sanitize_block_gap_value( $style[ $viewport ]['spacing']['blockGap'] ?? null ) );

                if ( null !== $viewport_gap ) {
                    $rules = array_merge( $rules, self::rules( $selector, $viewport_gap, $flow, $media_query ) );
                }
            }
        }

        if ( empty( $rules ) ) {
            return false;
        }

        wp_style_engine_get_stylesheet_from_css_rules(
            $rules,
            [
                'context'  => 'block-supports',
                'prettify' => false,
            ]
        );

        return true;
    }

    /**
     * Queues the gap rules for a block wrapper that carries $base_class, under a second, unique
     * class, so the rule beats the block stylesheet's default.
     *
     * @param array  $attributes The block's attributes.
     * @param string $inner      Selector appended to the wrapper's, when the children sit in an inner element.
     * @param bool   $flow       See add_rules().
     * @return string A space and the unique class, to append to the wrapper's class; '' when nothing was queued.
     */
    public static function wrapper_class( array $attributes, string $block_name, string $base_class, string $inner = '', bool $flow = false ): string {
        $class = wp_unique_id( $base_class . '-gap-' );

        return self::add_rules( $attributes['style'] ?? [], $block_name, '.' . $base_class . '.' . $class . $inner, $flow ) ? ' ' . $class : '';
    }

    /** A gap value as CSS, or null; a spacing preset becomes its custom property. */
    private static function css_value( $gap ): ?string {
        if ( ! is_string( $gap ) || '' === $gap ) {
            return null;
        }

        if ( str_contains( $gap, 'var:preset|spacing|' ) ) {
            return 'var(--wp--preset--spacing--' . _wp_to_kebab_case( substr( $gap, strrpos( $gap, '|' ) + 1 ) ) . ')';
        }

        return $gap;
    }

    /** One `gap` rule, or core's flow margins: none on the children, then $gap above each one after the first. */
    private static function rules( string $selector, string $gap, bool $flow, ?string $rules_group = null ): array {
        if ( ! $flow ) {
            return [ self::rule( $selector, [ 'gap' => $gap ], $rules_group ) ];
        }

        return [
            self::rule(
                $selector . ' > *',
                [
                    'margin-block-start' => '0',
                    'margin-block-end'   => '0',
                ],
                $rules_group
            ),
            self::rule( $selector . ' > * + *', [ 'margin-block-start' => $gap ], $rules_group ),
        ];
    }

    private static function rule( string $selector, array $declarations, ?string $rules_group = null ): array {
        return array_filter(
            [
                'selector'     => $selector,
                'declarations' => $declarations,
                'rules_group'  => $rules_group,
            ]
        );
    }
}
