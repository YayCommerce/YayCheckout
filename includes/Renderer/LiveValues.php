<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Live values: cart totals, cart counts and shopper fields that a merchant drops into Paragraph and
 * Heading text in the layout editor (packages/blocks/src/live-value/). Each is a span holding its key
 * and fallback; its text is only the editor's label.
 */
class LiveValues {

    use SingletonTrait;

    /** Mirrored by LIVE_VALUE_BLOCKS in packages/blocks/src/live-value/constants.ts: the blocks whose text can hold live values. */
    public const BLOCKS = [ 'core/paragraph', 'core/heading' ];

    /** Mirrored by LIVE_VALUE_CLASS in packages/blocks/src/live-value/constants.ts. */
    public const CLASS_NAME = 'yaycheckout-live-value';

    /** Mirrored by KEY_ATTR in packages/blocks/src/live-value/constants.ts. */
    public const KEY_ATTR = 'data-yaycheckout-value';

    /** Mirrored by FALLBACK_ATTR in packages/blocks/src/live-value/constants.ts. */
    public const FALLBACK_ATTR = 'data-fallback';

    /** Mirrored by FIELD_PREFIX in packages/blocks/src/live-value/constants.ts: a key naming a layout field rather than a cart value. */
    public const FIELD_PREFIX = 'field:';

    /** The layout editor's format script, packages/blocks/src/live-value/index.ts. */
    public const EDITOR_SCRIPT = 'yaycheckout-live-value-editor';

    /** The chip style in the layout editor's canvas, from packages/blocks/src/live-value/editor.css. */
    public const EDITOR_STYLE = 'yaycheckout-live-value-editor';

    /**
     * Cart value key => its name in the editor, in the picker's order. Keys ending in `_count` are the
     * picker's Counts group; the rest are money.
     *
     * @return array<string, string>
     */
    public static function cart_values(): array {
        return [
            'cart_total'          => __( 'Order total', 'yaycheckout' ),
            'cart_subtotal'       => __( 'Subtotal', 'yaycheckout' ),
            'cart_shipping_total' => __( 'Shipping', 'yaycheckout' ),
            'cart_discount_total' => __( 'Discount', 'yaycheckout' ),
            'cart_tax_total'      => __( 'Tax', 'yaycheckout' ),
            'cart_item_count'     => __( 'Item count', 'yaycheckout' ),
            'cart_line_count'     => __( 'Product count', 'yaycheckout' ),
        ];
    }
}
