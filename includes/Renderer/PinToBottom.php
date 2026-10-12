<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Pins a core Group to the bottom of the shopper's screen, on all screens or on mobile only.
 *
 * The class is added only on render, so the layout editor shows the Group in place: a fixed bar
 * inside the canvas would cover the blocks being edited. assets/css/checkout-base.css fixes the
 * bar, and assets/js/checkout-pin-to-bottom.js keeps the page's bottom clear of it.
 */
class PinToBottom {

    use SingletonTrait;

    /** The Group attribute: ALL, MOBILE, or anything else for off. */
    public const ATTRIBUTE = 'yaycheckoutPinToBottom';

    public const ALL = 'all';

    /** Pinned only below 782px, the breakpoint checkout-base.css owns; wider screens show the Group in its place. */
    public const MOBILE = 'mobile';

    public const PINNED_CLASS = 'yaycheckout-pinned';

    public const MOBILE_CLASS = 'yaycheckout-pinned--mobile';

    public const SCRIPT = 'yaycheckout-pin-to-bottom';

    /**
     * Not gated on CheckoutPage::layout(): only the layout editor offers the setting, and a Group
     * without it is returned untouched.
     */
    protected function __construct() {
        add_filter( 'render_block_core/group', [ $this, 'filter_group' ], 10, 2 );
    }

    /**
     * Marks a pinned Group with PINNED_CLASS, plus MOBILE_CLASS when it pins on mobile only.
     *
     * @param string $block_content Rendered Group, inner blocks included.
     * @param array  $block         Parsed Group block.
     */
    public function filter_group( $block_content, $block ): string {
        $block_content = (string) $block_content;
        $value         = is_array( $block ) && isset( $block['attrs'][ self::ATTRIBUTE ] ) ? $block['attrs'][ self::ATTRIBUTE ] : '';

        if ( self::ALL !== $value && self::MOBILE !== $value ) {
            return $block_content;
        }

        $html = new \WP_HTML_Tag_Processor( $block_content );

        if ( ! $html->next_tag() ) {
            return $block_content;
        }

        $html->add_class( self::PINNED_CLASS );

        if ( self::MOBILE === $value ) {
            $html->add_class( self::MOBILE_CLASS );
        }

        wp_enqueue_script( self::SCRIPT );

        return $html->get_updated_html();
    }
}
