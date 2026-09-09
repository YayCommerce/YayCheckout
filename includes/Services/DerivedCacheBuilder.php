<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Turns published block markup into the derived caches a customer request reads.
 *
 * Pure: a function of its input markup, with no storage reads or writes. That
 * is what lets publish() call this first and treat a failure as a no-op — bad
 * markup throws here, before anything has been written, so the previously
 * published layout keeps serving.
 *
 * Blocks declare what they contribute through a contributor map rather than
 * this class knowing every block type. Pro ships as a separate plugin and adds
 * its blocks through the filter without touching core.
 */
class DerivedCacheBuilder {

    public const FILTER_CONTRIBUTORS = 'yaycheckout_block_contributors';

    /** Shape of the derived array. Keys are stable; consumers rely on them. */
    private const EMPTY = [
        'fields'       => [],
        'extra_fields' => [],
        'slots'        => [],
        'order_modes'  => [],
    ];

    /**
     * @param  string $markup Published block markup.
     * @return array
     * @throws \InvalidArgumentException When the markup is not a usable layout.
     */
    public static function build( string $markup ): array {
        if ( '' === trim( $markup ) ) {
            throw new \InvalidArgumentException( 'Layout markup is empty.' );
        }

        $blocks = self::real_blocks( parse_blocks( $markup ) );

        if ( empty( $blocks ) ) {
            throw new \InvalidArgumentException( 'Layout markup contains no blocks.' );
        }

        $root = $blocks[0];

        if ( 'yaycheckout/checkout' !== $root['blockName'] ) {
            throw new \InvalidArgumentException(
                sprintf( 'Layout root must be yaycheckout/checkout, found %s.', $root['blockName'] )
            );
        }

        $derived = self::EMPTY;
        self::walk( $blocks, self::contributors(), $derived );

        return $derived;
    }

    /**
     * Block name => callable( array $block ): array returning a partial derived
     * array. Seeded with the blocks this plugin ships; extended via the filter.
     *
     * @return array
     */
    private static function contributors(): array {
        $map = [
            'yaycheckout/checkout' => [ self::class, 'contribute_checkout' ],
        ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- FILTER_CONTRIBUTORS is a yaycheckout_ prefixed constant.
        return (array) apply_filters( self::FILTER_CONTRIBUTORS, $map );
    }

    /**
     * The root block is a container: it holds sections but contributes no
     * fields, slots or order modes of its own. Present so the contributor
     * contract is visible to the blocks added in later phases and to Pro.
     *
     * @param  array $block
     * @return array
     */
    public static function contribute_checkout( array $block ): array {
        unset( $block );
        return [];
    }

    /**
     * @param array $blocks
     * @param array $contributors
     * @param array $derived      Accumulated by reference.
     */
    private static function walk( array $blocks, array $contributors, array &$derived ): void {
        foreach ( $blocks as $block ) {
            $name = $block['blockName'];

            if ( isset( $contributors[ $name ] ) && is_callable( $contributors[ $name ] ) ) {
                self::merge( $derived, (array) call_user_func( $contributors[ $name ], $block ) );
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                self::walk( self::real_blocks( $block['innerBlocks'] ), $contributors, $derived );
            }
        }
    }

    /**
     * Merge one block's contribution into the accumulated derived array.
     *
     * Fields and extra_fields are keyed by field name, so later blocks with the
     * same key win. Slots and order_modes are flat lists kept unique.
     *
     * @param array $derived
     * @param array $contribution
     */
    private static function merge( array &$derived, array $contribution ): void {
        foreach ( [ 'fields', 'extra_fields' ] as $key ) {
            if ( ! empty( $contribution[ $key ] ) ) {
                $derived[ $key ] = array_merge( $derived[ $key ], (array) $contribution[ $key ] );
            }
        }

        foreach ( [ 'slots', 'order_modes' ] as $key ) {
            if ( ! empty( $contribution[ $key ] ) ) {
                $derived[ $key ] = array_values(
                    array_unique( array_merge( $derived[ $key ], (array) $contribution[ $key ] ) )
                );
            }
        }
    }

    /**
     * Drops the entries parse_blocks() emits with a null blockName for the
     * whitespace between blocks, so callers can treat index 0 as the root.
     *
     * @param  array $blocks
     * @return array
     */
    private static function real_blocks( array $blocks ): array {
        return array_values(
            array_filter(
                $blocks,
                static function ( $block ) {
                    return ! empty( $block['blockName'] );
                }
            )
        );
    }
}
