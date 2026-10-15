<?php
namespace YayCheckout\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a watchable block is empty, for BlockVisibility's "Block is empty / not empty" condition.
 *
 * Watchable blocks (block.json support `yaycheckoutWatchable`) print BLOCK_ID_ATTR and, when
 * empty, EMPTY_ATTR on their wrapper; what "empty" means for each lives in its render.php and,
 * for Shipping methods and Payment, in CheckoutFragments, and for Express Pay, whose gateways fill it
 * after load, also in its view.ts. A block that prints nothing is empty.
 * packages/blocks/src/shared/visibility-rules.ts mirrors the markers and empty_ids().
 */
class BlockEmptiness {

    /** The block.json support key that makes a block pickable. */
    public const SUPPORT = 'yaycheckoutWatchable';

    public const BLOCK_ID_PATTERN = '/^[a-z0-9]{8}$/';

    public const BLOCK_ID_ATTR = 'data-yaycheckout-block-id';

    public const EMPTY_ATTR = 'data-yaycheckout-empty';

    /** On a fragment node update_order_review swaps: its EMPTY_ATTR is copied to the block wrapper. */
    public const LIVE_ATTR = 'data-yaycheckout-live';

    /** On a Field block's wrapper: its row is empty while address-i18n.js hides it for the country. */
    public const LOCALE_ATTR = 'data-yaycheckout-locale';

    /** A jQuery event on body that makes the visibility script re-check now; Field Value fires it as the shopper types. */
    public const REFRESH_EVENT = 'yaycheckout_visibility_refresh';

    /**
     * The ids among $ids that are empty in $html: no element carries the id without EMPTY_ATTR. An id no
     * element carries counts as empty, since a block that prints nothing has no wrapper.
     *
     * @param string[] $ids blockIds to check.
     * @return string[]
     */
    public static function empty_ids( string $html, array $ids ): array {
        $filled = [];
        $tags   = new \WP_HTML_Tag_Processor( $html );

        while ( $tags->next_tag() ) {
            $id = $tags->get_attribute( self::BLOCK_ID_ATTR );

            if ( is_string( $id ) && null === $tags->get_attribute( self::EMPTY_ATTR ) ) {
                $filled[ $id ] = true;
            }
        }

        return array_values( array_filter( $ids, static fn( $id ) => ! isset( $filled[ $id ] ) ) );
    }

    /**
     * A watchable block's wrapper attribute string: get_block_wrapper_attributes() with $base and
     * the block's markers. A block that prints nothing when empty leaves $is_empty false.
     */
    public static function wrapper( array $base, array $attributes, bool $is_empty = false ): string {
        return get_block_wrapper_attributes( array_merge( $base, self::wrapper_attributes( $attributes, $is_empty ) ) );
    }

    /**
     * Extra wrapper attributes for a watchable block, to merge into get_block_wrapper_attributes().
     *
     * @return array<string, string>
     */
    public static function wrapper_attributes( array $attributes, bool $is_empty ): array {
        $block_id = isset( $attributes['blockId'] ) && is_string( $attributes['blockId'] ) ? $attributes['blockId'] : '';

        if ( ! preg_match( self::BLOCK_ID_PATTERN, $block_id ) ) {
            return [];
        }

        $extra = [ self::BLOCK_ID_ATTR => $block_id ];

        if ( $is_empty ) {
            $extra[ self::EMPTY_ATTR ] = '1';
        }

        return $extra;
    }
}
