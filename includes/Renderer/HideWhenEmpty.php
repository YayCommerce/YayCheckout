<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Hides a core Group while every block the merchant ticked inside it is empty.
 *
 * Watchable blocks (block.json support `yaycheckoutWatchable`) print BLOCK_ID_ATTR and, when
 * empty, EMPTY_ATTR on their wrapper; what "empty" means for each lives in its render.php and,
 * for Shipping methods and Payment, in CheckoutFragments. A block that prints nothing is empty.
 */
class HideWhenEmpty {

    use SingletonTrait;

    /** The core/group attribute holding the blockIds it watches. */
    public const ATTRIBUTE = 'yaycheckoutHideWhenEmpty';

    public const HIDDEN_CLASS = 'yaycheckout-hidden-when-empty';

    /** The block.json support key that makes a block pickable. */
    public const SUPPORT = 'yaycheckoutWatchable';

    public const BLOCK_ID_PATTERN = '/^[a-z0-9]{8}$/';

    public const SCRIPT = 'yaycheckout-hide-when-empty';

    public const BLOCK_ID_ATTR = 'data-yaycheckout-block-id';

    public const EMPTY_ATTR = 'data-yaycheckout-empty';

    /** On a fragment node update_order_review swaps: its EMPTY_ATTR is copied to the block wrapper. */
    public const LIVE_ATTR = 'data-yaycheckout-live';

    /** On a decorated Group: the watched ids, space-separated, for the live script. */
    public const GROUP_ATTR = 'data-yaycheckout-hide-when-empty';

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
