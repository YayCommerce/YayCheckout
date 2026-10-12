<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Live values: cart totals, cart counts and shopper fields that a merchant drops into Paragraph and
 * Heading text in the layout editor (packages/blocks/src/live-value/). Each is a span holding its key
 * and fallback; its text is only the editor's label.
 *
 * On a taken-over checkout, fill() swaps each span's text for its value, or its fallback while the value
 * is empty. The cart's values reach view.js as a JSON element in the footer, which update_order_review
 * swaps as a fragment; view.js reads field values from the form as the shopper types.
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

    /** The storefront script, packages/blocks/src/live-value/view.ts; registered by BlockRegistry. */
    public const SCRIPT = 'yaycheckout-live-value';

    /** The storefront style, packages/blocks/src/live-value/view.css; registered by BlockRegistry. */
    public const STYLE = 'yaycheckout-live-value';

    /** The id of the footer's JSON element, and with `#` its update_order_review fragment. Read by view.ts. */
    public const VALUES_ID = 'yaycheckout-live-values';

    /** Set once fill() printed a span, so the footer prints the values view.js reads. */
    private $rendered = false;

    /** @var array<string, array{text: string, empty: bool}>|null The cart's values, read once per request by resolve(). */
    private $cart = null;

    protected function __construct() {
        foreach ( self::BLOCKS as $name ) {
            add_filter( 'render_block_' . $name, [ $this, 'fill' ], 10, 2 );
        }

        add_action( 'wp_footer', [ $this, 'print_values' ] );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
    }

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

    /**
     * Each cart value for the current cart, as text: money as the cart shows it (with tax when WooCommerce
     * shows prices with tax, so it matches the Totals block), counts as numbers. Empty at 0. [] without a cart.
     *
     * @return array<string, array{text: string, empty: bool}>
     */
    public static function values(): array {
        if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
            return [];
        }

        $cart = WC()->cart;
        $tax  = $cart->display_prices_including_tax();

        $money = [
            'cart_total'          => (float) $cart->get_total( 'edit' ),
            'cart_subtotal'       => (float) $cart->get_subtotal() + ( $tax ? (float) $cart->get_subtotal_tax() : 0.0 ),
            'cart_shipping_total' => (float) $cart->get_shipping_total() + ( $tax ? (float) $cart->get_shipping_tax() : 0.0 ),
            'cart_discount_total' => (float) $cart->get_discount_total() + ( $tax ? (float) $cart->get_discount_tax() : 0.0 ),
            'cart_tax_total'      => (float) $cart->get_total_tax(),
        ];

        $counts = [
            'cart_item_count' => (int) $cart->get_cart_contents_count(),
            'cart_line_count' => count( $cart->get_cart() ),
        ];

        $values = [];

        foreach ( $money as $key => $amount ) {
            $values[ $key ] = [
                // wc_price()'s markup and entities removed: the value is plain text, escaped where it prints.
                'text'  => html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ),
                'empty' => 0.0 === round( $amount, wc_get_price_decimals() ),
            ];
        }

        foreach ( $counts as $key => $count ) {
            $values[ $key ] = [
                'text'  => number_format_i18n( $count ),
                'empty' => 0 === $count,
            ];
        }

        return $values;
    }

    /**
     * Fills each live value span in a Paragraph or Heading on a taken-over checkout. Anywhere else, and in a
     * block without one, the HTML comes back untouched.
     *
     * @param string $block_content Rendered block.
     * @param array  $block         Parsed block.
     */
    public function fill( $block_content, $block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- render_block_{$name} passes it.
        $block_content = (string) $block_content;
        $page          = CheckoutPage::get_instance();

        if ( false === strpos( $block_content, self::CLASS_NAME ) || ! $page->takes_over() ) {
            return $block_content;
        }

        $markup = $page->layout()->content;
        $filled = preg_replace_callback(
            '#(<span\b[^>]*' . preg_quote( self::CLASS_NAME, '#' ) . '[^>]*>)(.*?)</span>#s',
            function ( array $found ) use ( $markup ): string {
                return $this->fill_span( $found[0], $found[1], $found[2], $markup );
            },
            $block_content
        );

        return is_string( $filled ) ? $filled : $block_content;
    }

    /** Prints the cart's values for view.js, once a span was filled. */
    public function print_values(): void {
        if ( $this->rendered ) {
            echo self::values_element(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG and JSON_HEX_AMP, so it can't close the element.
        }
    }

    /** Swaps the footer's values for the recalculated cart's, when the served layout holds a live value. */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout || false === strpos( $layout->content, self::CLASS_NAME ) ) {
            return $fragments;
        }

        $fragments[ '#' . self::VALUES_ID ] = self::values_element();

        return $fragments;
    }

    /** The cart's values as a JSON element; `<` and `&` are escaped, so no value can close it. */
    public static function values_element(): string {
        return '<script type="application/json" id="' . esc_attr( self::VALUES_ID ) . '">'
            . wp_json_encode( (object) self::values(), JSON_HEX_TAG | JSON_HEX_AMP )
            . '</script>';
    }

    /**
     * One span with its text set: the value, or the fallback while the value is empty and the fallback isn't
     * ''. A key the layout can't show, or a span holding tags, prints its fallback: never its label.
     */
    private function fill_span( string $span, string $opening, string $inner, string $markup ): string {
        $tags = new \WP_HTML_Tag_Processor( $opening );

        // The class name inside another class, or in some other attribute.
        if ( ! $tags->next_tag() || ! $tags->has_class( self::CLASS_NAME ) ) {
            return $span;
        }

        $key      = $tags->get_attribute( self::KEY_ATTR );
        $fallback = $tags->get_attribute( self::FALLBACK_ATTR );
        $fallback = is_string( $fallback ) ? $fallback : '';
        $value    = $this->resolve( is_string( $key ) ? $key : '', $markup );

        if ( ! $value['known'] || false !== strpos( $inner, '<' ) ) {
            $text = $fallback;
            // view.ts fills only spans with a key, so this one stays as printed: a key the layout can't show
            // could otherwise read a form field it was never meant to show, such as a password.
            $tags->remove_attribute( self::KEY_ATTR );
        } elseif ( $value['empty'] && '' !== $fallback ) {
            $text = $fallback;
        } else {
            $text = $value['text'];
        }

        if ( $value['empty'] || ! $value['known'] ) {
            $tags->add_class( 'is-empty' );
        }

        $this->rendered = true;
        // The layout renders before wp_head(), so the style prints in the head.
        wp_enqueue_script( self::SCRIPT );
        wp_enqueue_style( self::STYLE );

        return $tags->get_updated_html() . esc_html( $text ) . '</span>';
    }

    /**
     * A key's value: a cart value, or `field:<key>` for a field the layout can show. known is false for
     * any other key.
     *
     * @return array{text: string, empty: bool, known: bool}
     */
    private function resolve( string $key, string $markup ): array {
        if ( null === $this->cart ) {
            $this->cart = self::values();
        }

        if ( isset( $this->cart[ $key ] ) ) {
            return $this->cart[ $key ] + [ 'known' => true ];
        }

        $field = 0 === strpos( $key, self::FIELD_PREFIX ) ? substr( $key, strlen( self::FIELD_PREFIX ) ) : '';

        if ( '' === $field || ! isset( ShowableFields::labels( $markup )[ $field ] ) ) {
            return [
                'text'  => '',
                'empty' => true,
                'known' => false,
            ];
        }

        $text = ShowableFields::value( $field, $markup );

        return [
            'text'  => $text,
            'empty' => '' === $text,
            'known' => true,
        ];
    }
}
