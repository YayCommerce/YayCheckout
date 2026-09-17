<?php
namespace YayCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Published block markup → the derived caches a customer request reads.
 *
 * Must stay pure (no storage): publish() relies on a throw here meaning
 * nothing was written. Blocks contribute via the filter so Pro can attach
 * without core naming its blocks.
 */
class DerivedCacheBuilder {

    public const FILTER_CONTRIBUTORS = 'yaycheckout_block_contributors';

    /** Keys are a contract consumers rely on. */
    private const EMPTY = [
        'fields'       => [],
        'extra_fields' => [],
        'slots'        => [],
        'order_modes'  => [],
    ];

    /**
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

    /** Block name => callable( array $block ): array (partial derived array). */
    private static function contributors(): array {
        $map = [
            'yaycheckout/checkout' => [ self::class, 'contribute_checkout' ],
        ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- FILTER_CONTRIBUTORS is a yaycheckout_ prefixed constant.
        return (array) apply_filters( self::FILTER_CONTRIBUTORS, $map );
    }

    /** The root is a container; kept so the contributor contract has a visible example. */
    public static function contribute_checkout( array $block ): array {
        unset( $block );
        return [];
    }

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

    /** Later block wins on a duplicate field key; slots and order_modes are unique lists. */
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

    /** Drops the null-name entries parse_blocks() emits for whitespace, so index 0 is the root. */
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
