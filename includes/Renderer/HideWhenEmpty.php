<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;
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
     * Not gated on CheckoutPage::layout(): only a layout holds watchable blocks, and a Group
     * watching none of its descendants is returned untouched.
     */
    protected function __construct() {
        add_filter( 'render_block_core/group', [ $this, 'filter_group' ], 10, 2 );
    }

    /**
     * Marks a Group that watches blocks inside it for the live script, and hides it when all are empty.
     *
     * @param string $block_content Rendered Group, inner blocks included.
     * @param array  $block         Parsed Group block.
     */
    public function filter_group( $block_content, $block ): string {
        $block_content = (string) $block_content;
        $ids           = self::watched_descendant_ids( is_array( $block ) ? $block : [] );

        if ( empty( $ids ) ) {
            return $block_content;
        }

        $html = new \WP_HTML_Tag_Processor( $block_content );

        if ( ! $html->next_tag() ) {
            return $block_content;
        }

        $html->set_attribute( self::GROUP_ATTR, implode( ' ', $ids ) );

        if ( self::hidden( $block_content, $ids ) ) {
            $html->add_class( self::HIDDEN_CLASS );
        }

        wp_enqueue_script( self::SCRIPT );

        return $html->get_updated_html();
    }

    /**
     * True when every id is empty in $html: no element carries it without EMPTY_ATTR. An id no
     * element carries counts as empty, since a block that prints nothing has no wrapper.
     *
     * @param string[] $ids blockIds that are descendants of the Group.
     */
    public static function hidden( string $html, array $ids ): bool {
        if ( empty( $ids ) ) {
            return false;
        }

        $tags = new \WP_HTML_Tag_Processor( $html );

        while ( $tags->next_tag() ) {
            $id = $tags->get_attribute( self::BLOCK_ID_ATTR );

            if ( is_string( $id ) && in_array( $id, $ids, true ) && null === $tags->get_attribute( self::EMPTY_ATTR ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * The Group's watched ids that a watchable descendant still carries, so a stale id is ignored.
     *
     * @return string[]
     */
    private static function watched_descendant_ids( array $block ): array {
        $watched = isset( $block['attrs'][ self::ATTRIBUTE ] ) && is_array( $block['attrs'][ self::ATTRIBUTE ] ) ? $block['attrs'][ self::ATTRIBUTE ] : [];
        $watched = array_filter(
            $watched,
            static function ( $id ) {
                return is_string( $id ) && preg_match( self::BLOCK_ID_PATTERN, $id );
            }
        );

        if ( empty( $watched ) || empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
            return [];
        }

        $registry = \WP_Block_Type_Registry::get_instance();
        $present  = [];

        foreach ( LayoutMarkupReader::flatten( $block['innerBlocks'] ) as $inner ) {
            $type = $registry->get_registered( $inner['blockName'] );

            if ( $type && block_has_support( $type, self::SUPPORT ) && isset( $inner['attrs']['blockId'] ) && is_string( $inner['attrs']['blockId'] ) ) {
                $present[ $inner['attrs']['blockId'] ] = true;
            }
        }

        return array_values( array_unique( array_filter( $watched, static fn( $id ) => isset( $present[ $id ] ) ) ) );
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
