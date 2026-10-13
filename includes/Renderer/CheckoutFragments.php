<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping, payment and order summary HTML for render.php and update_order_review fragments,
 * plus the Place order button when it shows the order total.
 *
 * Stands down without a layout so Woo's payment fragment keeps Place order.
 */
class CheckoutFragments {

    use SingletonTrait;

    public const SHIPPING_RATES_CLASS = 'yaycheckout-shipping-methods__rates';

    public const ORDER_ITEMS_CLASS = 'yaycheckout-order-summary-items';

    public const ORDER_BREAKDOWN_CLASS = 'yaycheckout-order-summary-breakdown';

    public const ORDER_TOTAL_CLASS = 'yaycheckout-order-summary-total';

    /** Layout values the Payment block accepts; an unknown attribute falls back to joined. */
    public const PAYMENT_LAYOUTS = [ 'joined', 'checkmark', 'box', 'grid', 'cards', 'lines' ];

    /** Length key under style.yaycheckout => the --yaycheckout-payment-* variable it sets. LENGTH_VARS in style-paths.ts mirrors it; radius stays at style.border.radius. */
    private const PAYMENT_LENGTH_VARS = [
        'borderWidth' => '--yaycheckout-payment-border-width',
    ];

    /**
     * Colour key under style.yaycheckout => the --yaycheckout-payment-* variable it sets.
     * Idle background stays at style.color.background. COLOR_VARS in style-paths.ts mirrors both.
     */
    private const PAYMENT_COLOR_VARS = [
        'backgroundSelected' => '--yaycheckout-payment-bg-selected',
        'backgroundHover'    => '--yaycheckout-payment-bg-hover',
        'radio'              => '--yaycheckout-payment-radio',
        'radioSelected'      => '--yaycheckout-payment-radio-selected',
        'border'             => '--yaycheckout-payment-border',
        'borderSelected'     => '--yaycheckout-payment-border-selected',
        'borderHover'        => '--yaycheckout-payment-border-hover',
    ];

    /** Layout values the Shipping Methods block accepts; an unknown attribute falls back to joined. */
    public const SHIPPING_LAYOUTS = [ 'joined', 'checkmark', 'box', 'grid', 'cards', 'lines' ];

    /** Length key under style.yaycheckout => the --yaycheckout-shipping-* variable it sets. LENGTH_VARS in style-paths.ts mirrors it. */
    private const SHIPPING_LENGTH_VARS = [
        'borderWidth' => '--yaycheckout-shipping-border-width',
        'radius'      => '--yaycheckout-shipping-radius',
    ];

    /** Colour key under style.yaycheckout => the --yaycheckout-shipping-* variable it sets. COLOR_VARS in style-paths.ts mirrors it. */
    private const SHIPPING_COLOR_VARS = [
        'background'         => '--yaycheckout-shipping-bg',
        'backgroundSelected' => '--yaycheckout-shipping-bg-selected',
        'backgroundHover'    => '--yaycheckout-shipping-bg-hover',
        'radio'              => '--yaycheckout-shipping-radio',
        'radioSelected'      => '--yaycheckout-shipping-radio-selected',
        'border'             => '--yaycheckout-shipping-border',
        'borderSelected'     => '--yaycheckout-shipping-border-selected',
        'borderHover'        => '--yaycheckout-shipping-border-hover',
    ];

    /** Layout slot => [ selector WooCommerce's checkout.js replaces, method building its HTML ]. */
    private const FRAGMENTS = [
        'payment'          => [ '.woocommerce-checkout-payment', 'payment_methods_html' ],
        'shipping_methods' => [ '.' . self::SHIPPING_RATES_CLASS, 'shipping_rates_html' ],
    ];

    /**
     * Order Summary child block => [ selector checkout.js replaces, method building its HTML ]; found by block name, not slot.
     * Order Summary Total may appear several times, so fragment_selector() narrows its selector to each block's options.
     */
    private const BLOCK_FRAGMENTS = [
        'yaycheckout/order-summary-items'     => [ '.' . self::ORDER_ITEMS_CLASS, 'order_items_html' ],
        'yaycheckout/order-summary-breakdown' => [ '.' . self::ORDER_BREAKDOWN_CLASS, 'order_breakdown_html' ],
        'yaycheckout/order-summary-total'     => [ '.' . self::ORDER_TOTAL_CLASS, 'order_total_html' ],
    ];

    /** Woo's update_order_review fragment for its whole review-order table. */
    private const REVIEW_ORDER_TABLE = '.woocommerce-checkout-review-order-table';

    protected function __construct() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's template path filter.
        add_filter( 'wc_get_template', [ $this, 'skip_review_order_table' ], 10, 2 );
    }

    /**
     * During update_order_review, Woo renders its whole review-order table before the fragments
     * filter runs. A layout with Items, Order Summary Breakdown or Order Summary Total discards it, so swap it for an empty template
     * and its review-order hooks fire once, in our tables.
     */
    public function skip_review_order_table( $template, $template_name ) {
        if ( 'checkout/review-order.php' !== $template_name || ! doing_action( 'wc_ajax_update_order_review' ) ) {
            return $template;
        }

        $layout = CheckoutPage::get_instance()->layout();

        if ( null === $layout || empty( self::summary_block_fragments( $layout->content ) ) ) {
            return $template;
        }

        return YAYCHECKOUT_PLUGIN_DIR . 'templates/review-order-skipped.php';
    }

    /** Swaps the fragments of this layout's sections for this layout's HTML. */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout ) {
            return $fragments;
        }

        $rendered = array_flip( LayoutMarkupReader::slots( $layout->content ) );
        $summary  = self::summary_block_fragments( $layout->content );

        if ( ! empty( $summary ) ) {
            // Empty after skip_review_order_table(), and no element on the page matches it.
            unset( $fragments[ self::REVIEW_ORDER_TABLE ] );
        }

        $html = [];

        foreach ( array_intersect_key( self::FRAGMENTS, $rendered ) as [ $selector, $build ] ) {
            $html[ $selector ] = self::$build();
        }

        foreach ( $summary as [ $selector, $build, $attrs ] ) {
            $html[ $selector ] = self::$build( $attrs );
        }

        return array_merge( $fragments, array_filter( $html, 'strlen' ) );
    }

    /**
     * The BLOCK_FRAGMENTS entries whose block is in the layout, one per distinct selector, each with
     * the first such block's parsed attrs. Order Summary Total copies with the same options share one selector and HTML.
     *
     * @return array<int, array{0: string, 1: string, 2: array}>
     */
    private static function summary_block_fragments( string $markup ): array {
        $summary = [];

        foreach ( LayoutMarkupReader::flatten( parse_blocks( $markup ) ) as $block ) {
            $name = $block['blockName'];

            if ( ! is_string( $name ) || ! isset( self::BLOCK_FRAGMENTS[ $name ] ) ) {
                continue;
            }

            $selector = self::fragment_selector( $name, $block['attrs'] );

            if ( ! isset( $summary[ $selector ] ) ) {
                $summary[ $selector ] = [ $selector, self::BLOCK_FRAGMENTS[ $name ][1], $block['attrs'] ];
            }
        }

        return array_values( $summary );
    }

    /** The selector checkout.js replaces for one BLOCK_FRAGMENTS block: an Order Summary Total's names its options, the others are fixed. */
    private static function fragment_selector( string $name, array $attrs ): string {
        if ( 'yaycheckout/order-summary-total' === $name ) {
            return '.' . self::order_total_hash_class( self::order_total_options( $attrs ) );
        }

        return self::BLOCK_FRAGMENTS[ $name ][0];
    }

    public static function print_shipping_rates(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rate inputs match Woo's checkout contract; labels are its filtered HTML.
        echo self::shipping_rates_html();
    }

    /**
     * The Payment layout from block attributes, or joined when missing or unknown.
     *
     * @param array $attributes Block attributes.
     */
    public static function payment_layout( array $attributes ): string {
        $layout = isset( $attributes['layout'] ) && is_string( $attributes['layout'] ) ? $attributes['layout'] : '';

        return in_array( $layout, self::PAYMENT_LAYOUTS, true ) ? $layout : 'joined';
    }

    /**
     * Inline style attribute value of --yaycheckout-payment-* vars from the block's style object.
     * Empty when no Methods styles are set. Mirrors packages/blocks/.../style-paths.ts.
     *
     * @param array $attributes Block attributes.
     */
    public static function payment_methods_style_attribute( array $attributes ): string {
        $style   = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : [];
        $spacing = isset( $style['spacing'] ) && is_array( $style['spacing'] ) ? $style['spacing'] : [];
        $border  = isset( $style['border'] ) && is_array( $style['border'] ) ? $style['border'] : [];
        $color   = isset( $style['color'] ) && is_array( $style['color'] ) ? $style['color'] : [];
        $yay     = isset( $style['yaycheckout'] ) && is_array( $style['yaycheckout'] ) ? $style['yaycheckout'] : [];
        $decls   = [];

        $gap = self::css_gap( $spacing['blockGap'] ?? null );
        if ( '' !== $gap ) {
            $decls[] = '--yaycheckout-payment-gap:' . $gap;
        }

        $radius = self::css_length( $border['radius'] ?? null );
        if ( '' !== $radius ) {
            $decls[] = '--yaycheckout-payment-radius:' . $radius;
        }

        foreach ( self::PAYMENT_LENGTH_VARS as $key => $var ) {
            $length = self::css_length( $yay[ $key ] ?? null );
            if ( '' !== $length ) {
                $decls[] = $var . ':' . $length;
            }
        }

        $bg = self::css_color( $color['background'] ?? null );
        if ( '' !== $bg ) {
            $decls[] = '--yaycheckout-payment-bg:' . $bg;
        }

        foreach ( self::PAYMENT_COLOR_VARS as $key => $var ) {
            $value = self::css_color( $yay[ $key ] ?? null );
            if ( '' !== $value ) {
                $decls[] = $var . ':' . $value;
            }
        }

        // Text colour from color.text or the textColor preset slug (skipSerialization).
        $text = self::css_color( $color['text'] ?? null );
        if ( '' === $text && isset( $attributes['textColor'] ) && is_string( $attributes['textColor'] ) && '' !== $attributes['textColor'] ) {
            $text = 'var(--wp--preset--color--' . sanitize_title( $attributes['textColor'] ) . ')';
        }
        if ( '' !== $text ) {
            $decls[] = 'color:' . $text;
        }

        return implode( ';', $decls );
    }

    /**
     * The Shipping Methods layout from block attributes, or joined when missing or unknown.
     *
     * @param array $attributes Block attributes.
     */
    public static function shipping_layout( array $attributes ): string {
        $layout = isset( $attributes['layout'] ) && is_string( $attributes['layout'] ) ? $attributes['layout'] : '';

        return in_array( $layout, self::SHIPPING_LAYOUTS, true ) ? $layout : 'joined';
    }

    /**
     * Inline style attribute value of --yaycheckout-shipping-* vars from the block's style object.
     * Empty when no Methods styles are set. Mirrors packages/blocks/.../style-paths.ts.
     * Row radius and background live under style.yaycheckout: core's border and background style the wrapper.
     *
     * @param array $attributes Block attributes.
     */
    public static function shipping_methods_style_attribute( array $attributes ): string {
        $style   = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : [];
        $spacing = isset( $style['spacing'] ) && is_array( $style['spacing'] ) ? $style['spacing'] : [];
        $color   = isset( $style['color'] ) && is_array( $style['color'] ) ? $style['color'] : [];
        $yay     = isset( $style['yaycheckout'] ) && is_array( $style['yaycheckout'] ) ? $style['yaycheckout'] : [];
        $decls   = [];

        $gap = self::css_gap( $spacing['blockGap'] ?? null );
        if ( '' !== $gap ) {
            $decls[] = '--yaycheckout-shipping-gap:' . $gap;
        }

        foreach ( self::SHIPPING_LENGTH_VARS as $key => $var ) {
            $length = self::css_length( $yay[ $key ] ?? null );
            if ( '' !== $length ) {
                $decls[] = $var . ':' . $length;
            }
        }

        foreach ( self::SHIPPING_COLOR_VARS as $key => $var ) {
            $value = self::css_color( $yay[ $key ] ?? null );
            if ( '' !== $value ) {
                $decls[] = $var . ':' . $value;
            }
        }

        // Text colour from color.text or the textColor preset slug (skipSerialization).
        $text = self::css_color( $color['text'] ?? null );
        if ( '' === $text && isset( $attributes['textColor'] ) && is_string( $attributes['textColor'] ) && '' !== $attributes['textColor'] ) {
            $text = 'var(--wp--preset--color--' . sanitize_title( $attributes['textColor'] ) . ')';
        }
        if ( '' !== $text ) {
            $decls[] = 'color:' . $text;
        }

        return implode( ';', $decls );
    }

    /**
     * @param mixed $gap Block style spacing.blockGap.
     */
    private static function css_gap( $gap ): string {
        if ( ! is_string( $gap ) && ! is_numeric( $gap ) ) {
            return '';
        }

        $raw = is_string( $gap ) ? $gap : (string) $gap;
        if ( function_exists( 'wp_sanitize_block_gap_value' ) ) {
            $raw = wp_sanitize_block_gap_value( $raw );
        }

        if ( ! is_string( $raw ) || '' === $raw ) {
            return '';
        }

        if ( false !== strpos( $raw, 'var:preset|spacing|' ) ) {
            $slug = substr( $raw, strrpos( $raw, '|' ) + 1 );

            return 'var(--wp--preset--spacing--' . _wp_to_kebab_case( $slug ) . ')';
        }

        return self::css_safe_token( $raw );
    }

    /**
     * @param mixed $value Border radius string, number, or corner object (ignored).
     */
    private static function css_length( $value ): string {
        if ( is_numeric( $value ) ) {
            return absint( $value ) . 'px';
        }

        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }

        return self::css_safe_token( $value );
    }

    /**
     * @param mixed $value A CSS colour string or var:preset|color|slug.
     */
    private static function css_color( $value ): string {
        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }

        if ( 0 === strpos( $value, 'var:preset|color|' ) ) {
            $slug = substr( $value, strlen( 'var:preset|color|' ) );

            return '' !== $slug ? 'var(--wp--preset--color--' . _wp_to_kebab_case( $slug ) . ')' : '';
        }

        $hex = sanitize_hex_color( $value );
        if ( $hex ) {
            return $hex;
        }

        return self::css_safe_token( $value );
    }

    /** Rejects values that could break out of a CSS custom-property declaration. */
    private static function css_safe_token( string $value ): string {
        if ( preg_match( '/[=<>{}]|\/\*/', $value ) ) {
            return '';
        }

        return $value;
    }

    public static function print_payment_methods(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for gateways.
        echo self::payment_methods_html();
    }

    /** Shipping methods is empty when the cart needs no shipping; the "enter your address" and "no options" messages count as content. */
    public static function shipping_empty(): bool {
        return ! WC()->cart instanceof \WC_Cart || ! ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() );
    }

    /** Payment is empty when the order needs no payment; the "no available payment methods" notice counts as content. */
    public static function payment_empty(): bool {
        return ! WC()->cart instanceof \WC_Cart || ! WC()->cart->needs_payment();
    }

    /** HideWhenEmpty's markers for a fragment root, so the live script reads emptiness from the swapped HTML. */
    private static function live_attributes( bool $is_empty ): string {
        return ' ' . HideWhenEmpty::LIVE_ATTR . '="1"' . ( $is_empty ? ' ' . HideWhenEmpty::EMPTY_ATTR . '="1"' : '' );
    }

    /**
     * Rates wrapper so AJAX can replace an empty first paint. The list is this block's markup;
     * each rate still posts Woo's shipping_method input and runs woocommerce_after_shipping_rate.
     * Review-order shipping hooks fire around the list, as review-order.php does, since Order
     * Summary Breakdown leaves the rates to this block. Plugins on those hooks print table rows, so each hook's
     * output sits in its own table.
     */
    public static function shipping_rates_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $empty = self::shipping_empty();

        ob_start();

        echo '<div class="' . esc_attr( self::SHIPPING_RATES_CLASS ) . '"' . self::live_attributes( $empty ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- live_attributes() prints fixed names and values.

        if ( ! $empty ) {
            self::print_shipping_extension_rows( 'woocommerce_review_order_before_shipping' );
            self::print_shipping_packages();
            self::print_shipping_extension_rows( 'woocommerce_review_order_after_shipping' );
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * One list per package, from the same packages wc_cart_totals_shipping_html() reads.
     * Checkout never shows the cart calculator: Woo gates that with is_cart().
     */
    private static function print_shipping_packages(): void {
        $packages = WC()->shipping()->get_packages();

        if ( ! is_array( $packages ) ) {
            return;
        }

        $package_count = count( $packages );
        $chosen        = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        $chosen        = is_array( $chosen ) ? $chosen : array();

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Woo's shipping hooks and strings, so its translation still applies.
        foreach ( $packages as $index => $package ) {
            if ( ! is_array( $package ) ) {
                continue;
            }

            $methods       = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
            $chosen_method = isset( $chosen[ $index ] ) ? $chosen[ $index ] : '';
            $destination   = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
            $formatted     = WC()->countries->get_formatted_address( $destination, ', ' );
            $calculated    = WC()->customer && WC()->customer->has_calculated_shipping();
            $package_name  = isset( $package['package_name'] ) ? $package['package_name'] : '';
            $name_class    = 'yaycheckout-shipping-methods__package-name' . ( 1 === $package_count ? ' yaycheckout-shipping-methods__package-name--solo' : '' );

            echo '<div class="yaycheckout-shipping-methods__package">';

            if ( is_string( $package_name ) && '' !== $package_name ) {
                echo '<p class="' . esc_attr( $name_class ) . '">' . wp_kses_post( $package_name ) . '</p>';
            }

            if ( ! empty( $methods ) ) {
                self::print_shipping_method_list( $methods, (int) $index, $chosen_method );
            } elseif ( ! $calculated || ! $formatted ) {
                echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'woocommerce' ) ) );
            } else {
                echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) );
            }

            if ( 1 < $package_count ) {
                self::print_shipping_package_details( $package );
            }

            echo '</div>';
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch
    }

    /**
     * Radios Woo's checkout.js listens for. The label is the method name after
     * woocommerce_cart_shipping_method_full_label, and the price is its own element:
     * core concatenates them, which leaves no node for a card layout to place.
     *
     * @param array<string, \WC_Shipping_Rate> $methods
     * @param mixed                            $chosen_method Session value for this package.
     */
    private static function print_shipping_method_list( array $methods, int $index, $chosen_method ): void {
        echo '<ul id="shipping_method" class="woocommerce-shipping-methods yaycheckout-shipping-methods__list">';

        foreach ( $methods as $method ) {
            if ( ! $method instanceof \WC_Shipping_Rate ) {
                continue;
            }

            $slug     = sanitize_title( $method->id );
            $input_id = 'shipping_method_' . $index . '_' . $slug;
            $name     = apply_filters(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's rate-label filter.
                'woocommerce_cart_shipping_method_full_label',
                $method->get_label(),
                $method
            );
            $name  = is_string( $name ) ? $name : $method->get_label();
            $price = self::shipping_rate_price_html( $method );

            $attr_index = (string) $index;

            echo '<li class="yaycheckout-shipping-methods__option">';

            if ( 1 < count( $methods ) ) {
                echo '<input type="radio" name="shipping_method[' . esc_attr( $attr_index ) . ']" data-index="' . esc_attr( $attr_index ) . '" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( $method->id ) . '" class="shipping_method" ' . checked( $method->id, $chosen_method, false ) . ' />';
            } else {
                echo '<input type="hidden" name="shipping_method[' . esc_attr( $attr_index ) . ']" data-index="' . esc_attr( $attr_index ) . '" id="' . esc_attr( $input_id ) . '" value="' . esc_attr( $method->id ) . '" class="shipping_method" />';
            }

            echo '<label for="' . esc_attr( $input_id ) . '">';
            echo '<span class="yaycheckout-shipping-methods__indicator" aria-hidden="true"></span>';
            echo '<span class="yaycheckout-shipping-methods__body"><span class="yaycheckout-shipping-methods__name">';
            echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered rate label, the HTML Woo prints inside the label.
            echo '</span></span>';

            if ( '' !== $price ) {
                echo '<span class="yaycheckout-shipping-methods__price">' . $price . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() HTML, plus an escaped tax label.
            }

            echo '</label>';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's per-rate hook; plugins print pickup and delivery fields here.
            do_action( 'woocommerce_after_shipping_rate', $method, $index );
            echo '</li>';
        }

        echo '</ul>';
    }

    /** The price suffix wc_cart_totals_shipping_method_label() appends, without the method name. */
    private static function shipping_rate_price_html( \WC_Shipping_Rate $method ): string {
        $has_cost  = 0 < $method->cost;
        $hide_cost = ! $has_cost && in_array( $method->get_method_id(), array( 'free_shipping', 'local_pickup' ), true );

        if ( ! $has_cost || $hide_cost ) {
            return '';
        }

        if ( WC()->cart->display_prices_including_tax() ) {
            $html = wc_price( $method->cost + $method->get_shipping_tax() );

            if ( $method->get_shipping_tax() > 0 && ! wc_prices_include_tax() ) {
                $html .= ' <small class="tax_label">' . esc_html( WC()->countries->inc_tax_or_vat() ) . '</small>';
            }

            return $html;
        }

        $html = wc_price( $method->cost );

        if ( $method->get_shipping_tax() > 0 && wc_prices_include_tax() ) {
            $html .= ' <small class="tax_label">' . esc_html( WC()->countries->ex_tax_or_vat() ) . '</small>';
        }

        return $html;
    }

    /** Product names for a package, as wc_cart_totals_shipping_html() builds them when several ship. */
    private static function print_shipping_package_details( array $package ): void {
        $product_names = array();

        if ( isset( $package['contents'] ) && is_array( $package['contents'] ) ) {
            foreach ( $package['contents'] as $item_id => $values ) {
                if ( ! is_array( $values ) || ! isset( $values['data'] ) || ! $values['data'] instanceof \WC_Product ) {
                    continue;
                }

                $product_names[ $item_id ] = $values['data']->get_name() . ' &times;' . $values['quantity'];
            }
        }

        $product_names = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's package-details filter.
            'woocommerce_shipping_package_details_array',
            $product_names,
            $package
        );

        if ( ! is_array( $product_names ) || array() === $product_names ) {
            return;
        }

        echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( implode( ', ', $product_names ) ) . '</small></p>';
    }

    /**
     * Output from a review-order shipping hook. Plugins usually print <tr> rows
     * (needs a table); anything else (div, notice) goes in a plain wrapper so it
     * is not forced into <tbody>.
     */
    private static function print_shipping_extension_rows( string $hook ): void {
        ob_start();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Caller passes Woo's review-order shipping hooks.
        do_action( $hook );
        $html = (string) ob_get_clean();

        if ( '' === trim( $html ) ) {
            return;
        }

        if ( 1 === preg_match( '/^\s*<tr[\s>]/i', $html ) ) {
            echo '<table class="shop_table yaycheckout-shipping-methods__extensions"><tbody>';
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML plugins print on Woo's review-order shipping hooks.
            echo '</tbody></table>';
            return;
        }

        echo '<div class="yaycheckout-shipping-methods__extensions">';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML plugins print on Woo's review-order shipping hooks.
        echo '</div>';
    }

    /** Gateway radios only; Place order is a separate block. */
    public static function payment_methods_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $empty = self::payment_empty();

        if ( ! $empty ) {
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            WC()->payment_gateways()->set_current_gateway( $available_gateways );
        } else {
            $available_gateways = [];
        }

        ob_start();

        if ( ! wp_doing_ajax() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_before_payment' );
        }
        ?>
<div id="payment" class="woocommerce-checkout-payment"<?php echo self::live_attributes( $empty ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- live_attributes() prints fixed names and values. ?>>
        <?php if ( ! $empty ) : ?>
    <ul class="wc_payment_methods payment_methods methods" aria-label="<?php esc_attr_e( 'Payment methods', 'yaycheckout' ); ?>">
            <?php
            if ( ! empty( $available_gateways ) ) {
                foreach ( $available_gateways as $gateway ) {
                    wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
                }
            } else {
                echo '<li>';
                wc_print_notice(
                    apply_filters(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
                        'woocommerce_no_available_payment_methods_message',
                        WC()->customer->get_billing_country()
                            ? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'yaycheckout' )
                            : esc_html__( 'Please fill in your details above to see available payment methods.', 'yaycheckout' )
                    ),
                    'notice'
                );
                echo '</li>';
            }
            ?>
    </ul>
        <?php endif; ?>
</div>
        <?php
        if ( ! wp_doing_ajax() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic checkout contract.
            do_action( 'woocommerce_review_order_after_payment' );
        }

        return (string) ob_get_clean();
    }

    /** An Items, Order Summary Breakdown or Order Summary Total block: its wrapper and the fragment root fragments() refreshes. */
    public static function print_summary_block( \WP_Block $block ): void {
        if ( ! isset( self::BLOCK_FRAGMENTS[ $block->name ] ) ) {
            return;
        }

        $build   = self::BLOCK_FRAGMENTS[ $block->name ][1];
        $wrapper = [];
        $stepper = false;

        // Items only. Its gap class, nonce and notices sit on the wrapper because checkout.js replaces the root on every refresh.
        if ( 'yaycheckout/order-summary-items' === $block->name ) {
            // Block spacing is the gap between rows.
            $class = wp_unique_id( 'yaycheckout-order-summary-items-gap-' );

            if ( BlockGap::add_rules( $block->attributes['style'] ?? [], $block->name, '.' . $class . ' > .' . self::ORDER_ITEMS_CLASS ) ) {
                $wrapper['class'] = $class;
            }

            $badge_style = self::order_items_badge_style( $block->attributes );
            if ( '' !== $badge_style ) {
                $wrapper['style'] = $badge_style;
            }

            $stepper = self::order_items_options( $block->attributes )['show_stepper'];

            if ( $stepper ) {
                $wrapper['data-yaycheckout-cart-nonce'] = wp_create_nonce( CartItemQuantity::NONCE_ACTION );
                $wrapper['data-error-text']             = CartItemQuantity::retry_message();
            }
        }

        // Breakdown and Total: their Label and Value styles, on the wrapper for the same reason.
        if ( 'yaycheckout/order-summary-breakdown' === $block->name || 'yaycheckout/order-summary-total' === $block->name ) {
            $table_class = 'yaycheckout/order-summary-total' === $block->name ? self::ORDER_TOTAL_CLASS : self::ORDER_BREAKDOWN_CLASS;
            $parts_class = SummaryPartStyles::wrapper_class( $block->attributes, wp_get_block_default_classname( $block->name ), $table_class );

            if ( '' !== $parts_class ) {
                $wrapper['class'] = trim( $parts_class );
            }
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
        echo '<div ' . get_block_wrapper_attributes( $wrapper ) . '>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for review-order rows.
        echo self::$build( $block->attributes );

        if ( $stepper ) {
            echo '<div class="' . esc_attr( self::ORDER_ITEMS_CLASS . '__notices' ) . '" aria-live="polite"></div>';
        }

        echo '</div>';
    }

    /**
     * Inline --yaycheckout-badge-* vars from the Items block's badge attributes; empty when none
     * are set. On the wrapper, which checkout.js keeps across refreshes. Mirrors badgeStyle() in
     * order-summary-items/edit.tsx.
     *
     * @param array $attributes Block attributes.
     */
    private static function order_items_badge_style( array $attributes ): string {
        $decls = [];

        $colors = [
            'badgeBackgroundColor' => '--yaycheckout-badge-bg',
            'badgeTextColor'       => '--yaycheckout-badge-text',
        ];
        foreach ( $colors as $key => $var ) {
            $value = self::css_color( $attributes[ $key ] ?? null );
            if ( '' !== $value ) {
                $decls[] = $var . ':' . $value;
            }
        }

        if ( isset( $attributes['badgeSize'] ) && is_numeric( $attributes['badgeSize'] ) ) {
            $decls[] = '--yaycheckout-badge-size:' . max( 16, min( 32, (int) $attributes['badgeSize'] ) ) . 'px';
        }

        return implode( ';', $decls );
    }

    /**
     * The Items block's attributes with their defaults. During AJAX they come from the parsed
     * layout, where an untouched attribute is missing, so each default matches block.json's.
     *
     * @return array{show_labels: bool, product_label: string, subtotal_label: string, show_thumbnails: bool, item_data: string, show_sku: bool, show_unit_price: bool, show_short_description: bool, quantity_display: string, show_stepper: bool}
     */
    public static function order_items_options( array $attributes ): array {
        // The first choice is the default.
        $choice = static function ( string $name, array $choices ) use ( $attributes ): string {
            return isset( $attributes[ $name ] ) && in_array( $attributes[ $name ], $choices, true ) ? $attributes[ $name ] : $choices[0];
        };

        return [
            'show_labels'            => self::bool_attr( $attributes, 'showLabels', true ),
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Woo's own header word, so its translation still applies.
            'product_label'          => LayoutMarkupReader::plain_text( $attributes, 'productLabel', __( 'Product', 'woocommerce' ) ),
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Woo's own header word, so its translation still applies.
            'subtotal_label'         => LayoutMarkupReader::plain_text( $attributes, 'subtotalLabel', __( 'Subtotal', 'woocommerce' ) ),
            'show_thumbnails'        => self::bool_attr( $attributes, 'showThumbnails', true ),
            'item_data'              => $choice( 'itemData', [ 'list', 'inline', 'hidden' ] ),
            'show_sku'               => self::bool_attr( $attributes, 'showSku', false ),
            'show_unit_price'        => self::bool_attr( $attributes, 'showUnitPrice', false ),
            'show_short_description' => self::bool_attr( $attributes, 'showShortDescription', false ),
            'quantity_display'       => $choice( 'quantityDisplay', [ 'inline', 'badge' ] ),
            'show_stepper'           => self::bool_attr( $attributes, 'showStepper', false ),
        ];
    }

    /**
     * The cart rows of Woo's checkout/review-order.php as divs, without its table class so Woo's
     * own review-order fragment cannot replace it with the whole table. A table has no room for a
     * thumbnail beside the name, so only the cart-contents hooks keep one: extensions echo table
     * rows into them, which print as loose text outside a table.
     */
    public static function order_items_html( array $attributes = [] ): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $options = self::order_items_options( $attributes );
        // A badge sits on the thumbnail; without one, the quantity shows inline rather than vanish.
        $badge = 'badge' === $options['quantity_display'] && $options['show_thumbnails'];
        $root  = self::ORDER_ITEMS_CLASS . ( 'inline' === $options['item_data'] ? ' ' . self::ORDER_ITEMS_CLASS . '--item-data-inline' : '' );

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's review-order hooks and filters.
        ob_start();
        ?>
<div class="<?php echo esc_attr( $root ); ?>">
        <?php if ( $options['show_labels'] ) : ?>
    <div class="<?php echo esc_attr( self::ORDER_ITEMS_CLASS . '__header' ); ?>">
        <span class="product-name"><?php echo esc_html( $options['product_label'] ); ?></span>
        <span class="product-total"><?php echo esc_html( $options['subtotal_label'] ); ?></span>
    </div>
        <?php endif; ?>
        <?php
        self::print_order_items_hook( 'woocommerce_review_order_before_cart_contents' );

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

            if ( ! $product instanceof \WC_Product || ! $product->exists() || $cart_item['quantity'] <= 0 || ! $visible ) {
                continue;
            }

            // A product with one allowed quantity gets no stepper; its quantity shows as the Quantity option says.
            $stepper = $options['show_stepper']
                && ! $product->is_sold_individually()
                && $product->get_min_purchase_quantity() !== $product->get_max_purchase_quantity();
            ?>
    <div class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) . ' ' . self::ORDER_ITEMS_CLASS . '__item' ); ?>">
            <?php if ( $options['show_thumbnails'] ) : ?>
        <div class="product-thumbnail">
                <?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image(), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's filtered image HTML, as cart/cart.php prints it. ?>
                <?php if ( $badge ) : ?>
            <span class="<?php echo esc_attr( self::ORDER_ITEMS_CLASS . '__badge' ); ?>"><span aria-hidden="true"><?php echo esc_html( $cart_item['quantity'] ); ?></span><span class="screen-reader-text">
                    <?php
                    /* translators: %s: quantity */
                    echo esc_html( sprintf( __( 'Quantity: %s', 'yaycheckout' ), $cart_item['quantity'] ) );
                    ?>
            </span></span>
                <?php endif; ?>
        </div>
            <?php endif; ?>
        <div class="product-name">
            <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
            <?php // The stepper's input shows the quantity, so inline it would say it twice. ?>
            <?php if ( ! $badge && ! $stepper ) : ?>
                <?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's filtered HTML. ?>
            <?php endif; ?>
            <?php
            if ( 'hidden' !== $options['item_data'] ) {
                echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo escapes item data.
            }

            self::print_item_details( $options, $product, $cart_item, $cart_item_key );

            if ( $stepper ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart quantity filter, so an extension that locks an item's quantity locks it here.
                echo apply_filters( 'woocommerce_cart_item_quantity', self::stepper_html( $product, $cart_item, $cart_item_key ), $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in stepper_html(), then Woo's filtered HTML.
            }

            if ( $options['show_stepper'] ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart remove-link filter.
                echo apply_filters( 'woocommerce_cart_item_remove_link', self::remove_link_html( $product, $cart_item_key ), $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in remove_link_html(), then Woo's filtered HTML.
            }
            ?>
        </div>
        <div class="product-total">
            <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's price HTML. ?>
        </div>
    </div>
            <?php
        }

        self::print_order_items_hook( 'woocommerce_review_order_after_cart_contents' );
        ?>
</div>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        return (string) ob_get_clean();
    }

    /** The SKU, unit price and short description under an item's name, each when its option is on. */
    private static function print_item_details( array $options, \WC_Product $product, array $cart_item, string $cart_item_key ): void {
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart filter.
        $sku = $options['show_sku'] ? $product->get_sku() : '';

        if ( '' !== $sku ) {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Woo's own label, so its translation still applies.
            echo '<div class="product-sku">' . esc_html__( 'SKU:', 'woocommerce' ) . ' ' . esc_html( $sku ) . '</div>';
        }

        if ( $options['show_unit_price'] ) {
            $price = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $cart_item, $cart_item_key );
            /* translators: %s: product price */
            echo '<div class="product-unit-price">' . sprintf( esc_html__( '%s each', 'yaycheckout' ), $price ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's filtered price HTML.
        }

        if ( $options['show_short_description'] ) {
            $text   = $product->get_short_description();
            $parent = '' === $text && $product->get_parent_id() > 0 ? wc_get_product( $product->get_parent_id() ) : null;
            $text   = $parent instanceof \WC_Product ? $parent->get_short_description() : $text;
            // A literal ellipsis: the default &hellip; would print as text through esc_html().
            $text = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ) ), 20, '…' );

            if ( '' !== $text ) {
                echo '<div class="product-short-description">' . esc_html( $text ) . '</div>';
            }
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }

    /**
     * − / input / + for one cart item. The input has no name, so the checkout form never posts
     * it, and every button is type="button", so neither places the order.
     */
    private static function stepper_html( \WC_Product $product, array $cart_item, string $cart_item_key ): string {
        $name     = wp_strip_all_tags( $product->get_name() );
        $quantity = (int) $cart_item['quantity'];
        $max      = $product->get_max_purchase_quantity();
        $button   = static function ( int $step, string $label, string $text, bool $disabled ): string {
            return '<button type="button" class="yaycheckout-qty__button" data-step="' . esc_attr( (string) $step ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $disabled ? ' disabled' : '' ) . '>' . esc_html( $text ) . '</button>';
        };

        return '<div class="yaycheckout-qty">'
            /* translators: %s: product name */
            . $button( -1, sprintf( __( 'Decrease quantity of %s', 'yaycheckout' ), $name ), '−', $quantity <= 1 )
            . '<input type="number" class="yaycheckout-qty__input" value="' . esc_attr( (string) $quantity ) . '" min="1"' . ( $max > 0 ? ' max="' . esc_attr( (string) $max ) . '"' : '' ) . ' step="1" inputmode="numeric" data-cart-item-key="' . esc_attr( $cart_item_key ) . '"'
            /* translators: %s: product name */
            . ' aria-label="' . esc_attr( sprintf( __( 'Quantity of %s', 'yaycheckout' ), $name ) ) . '">'
            /* translators: %s: product name */
            . $button( 1, sprintf( __( 'Increase quantity of %s', 'yaycheckout' ), $name ), '+', $max > 0 && $quantity >= $max )
            . '</div>';
    }

    /** The remove link, which still works without JavaScript through WooCommerce's cart URL. */
    private static function remove_link_html( \WC_Product $product, string $cart_item_key ): string {
        return '<a href="' . esc_url( wc_get_cart_remove_url( $cart_item_key ) ) . '" class="remove ' . esc_attr( self::ORDER_ITEMS_CLASS . '__remove' ) . '" data-cart-item-key="' . esc_attr( $cart_item_key ) . '"'
            /* translators: %s: product name */
            . ' aria-label="' . esc_attr( sprintf( __( 'Remove %s from cart', 'yaycheckout' ), wp_strip_all_tags( $product->get_name() ) ) ) . '">&times;</a>';
    }

    /** Fires a cart-contents hook and prints its output, if any, in a table of its own. */
    private static function print_order_items_hook( string $hook ): void {
        ob_start();
        do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Woo's review-order hooks, named by the caller.
        $output = (string) ob_get_clean();

        if ( '' === trim( $output ) ) {
            return;
        }

        echo '<table class="shop_table ' . esc_attr( self::ORDER_ITEMS_CLASS . '__hook' ) . '"><tbody>';
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Extensions' own review-order rows.
        echo '</tbody></table>';
    }

    /** A boolean block attribute, or $fallback when it is missing or not a boolean. */
    private static function bool_attr( array $attributes, string $name, bool $fallback ): bool {
        return isset( $attributes[ $name ] ) && is_bool( $attributes[ $name ] ) ? $attributes[ $name ] : $fallback;
    }

    /**
     * The Order Summary Breakdown block's attributes with their defaults. During AJAX they come from the parsed
     * layout, where an untouched attribute is missing, so each default matches block.json's.
     *
     * @return array{show_subtotal: bool, subtotal_label: string}
     */
    public static function order_breakdown_options( array $attributes ): array {
        return [
            'show_subtotal'  => self::bool_attr( $attributes, 'showSubtotal', true ),
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Woo's own label, so its translation still applies.
            'subtotal_label' => LayoutMarkupReader::plain_text( $attributes, 'subtotalLabel', __( 'Subtotal', 'woocommerce' ) ),
        ];
    }

    /**
     * The tfoot rows of Woo's checkout/review-order.php, as the body of their own table. Shipping
     * is one read-only row (method name / Free); the Shipping Methods block keeps the radios. The
     * order-total row is the Order Summary Total block's; its hooks stay here, so extensions' rows print once.
     *
     * @param array $attributes Order Summary Breakdown block attributes; see order_breakdown_options().
     */
    public static function order_breakdown_html( array $attributes = [] ): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $options = self::order_breakdown_options( $attributes );

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Woo's review-order hooks and strings, so its translation still applies.
        ob_start();
        ?>
<table class="shop_table <?php echo esc_attr( self::ORDER_BREAKDOWN_CLASS ); ?>">
    <tbody>
        <?php if ( $options['show_subtotal'] ) : ?>
        <tr class="cart-subtotal">
            <th><?php echo esc_html( $options['subtotal_label'] ); ?></th>
            <td><?php wc_cart_totals_subtotal_html(); ?></td>
        </tr>
        <?php endif; ?>
        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
        <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
            <td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php self::print_order_shipping_row(); ?>
        <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
        <tr class="fee">
            <th><?php echo esc_html( $fee->name ); ?></th>
            <td><?php wc_cart_totals_fee_html( $fee ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
            <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
        <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <th><?php echo esc_html( $tax->label ); ?></th>
            <td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
        </tr>
                <?php endforeach; ?>
            <?php else : ?>
        <tr class="tax-total">
            <th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
            <td><?php wc_cart_totals_taxes_total_html(); ?></td>
        </tr>
            <?php endif; ?>
        <?php endif; ?>
        <?php // The order-total row between these hooks is the Order Summary Total block's; extensions' rows stay here. ?>
        <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
        <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
    </tbody>
</table>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch

        return (string) ob_get_clean();
    }

    /**
     * The Order Summary Total block's attributes with their defaults. During AJAX they come from the parsed
     * layout, where an untouched attribute is missing, so each default matches block.json's.
     *
     * @return array{show_label: bool, label: string, show_currency_code: bool}
     */
    public static function order_total_options( array $attributes ): array {
        return [
            'show_label'         => self::bool_attr( $attributes, 'showLabel', true ),
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Woo's own label, so its translation still applies.
            'label'              => LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Total', 'woocommerce' ) ),
            'show_currency_code' => self::bool_attr( $attributes, 'showCurrencyCode', false ),
        ];
    }

    /**
     * The class naming one Order Summary Total block's fragment. Hashed from its options rather than its raw attrs,
     * so render.php (defaults filled) and the parsed layout (defaults missing) agree.
     *
     * @param array $options order_total_options() output.
     */
    private static function order_total_hash_class( array $options ): string {
        return self::ORDER_TOTAL_CLASS . '--' . substr( md5( (string) wp_json_encode( $options ) ), 0, 8 );
    }

    /**
     * Woo's order-total row of checkout/review-order.php as its own table, without the hooks around
     * it: Order Summary Breakdown fires those, so several Order Summary Total blocks never repeat extensions' rows.
     *
     * @param array $attributes Order Summary Total block attributes; see order_total_options().
     */
    public static function order_total_html( array $attributes = [] ): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $options = self::order_total_options( $attributes );

        ob_start();
        ?>
<table class="shop_table <?php echo esc_attr( self::ORDER_TOTAL_CLASS . ' ' . self::order_total_hash_class( $options ) ); ?>">
    <tbody>
        <tr class="order-total">
            <?php if ( $options['show_label'] ) : ?>
            <th><?php echo esc_html( $options['label'] ); ?></th>
            <?php endif; ?>
            <td>
                <?php if ( $options['show_currency_code'] ) : ?>
                <span class="<?php echo esc_attr( self::ORDER_TOTAL_CLASS . '__currency' ); ?>"><?php echo esc_html( get_woocommerce_currency() ); ?></span>
                <?php endif; ?>
                <?php wc_cart_totals_order_total_html(); ?>
            </td>
        </tr>
    </tbody>
</table>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * One read-only shipping line for the totals table: the selected method name(s), and Free or
     * the shipping amount. Radios stay in the Shipping Methods block.
     */
    private static function print_order_shipping_row(): void {
        if ( ! WC()->cart instanceof \WC_Cart || ! WC()->cart->needs_shipping() ) {
            return;
        }

        $packages = WC()->shipping()->get_packages();
        $packages = is_array( $packages ) ? $packages : array();
        $chosen   = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        $chosen   = is_array( $chosen ) ? $chosen : array();

        $selected_names  = array();
        $available_names = array();
        $has_selected    = false;

        foreach ( $packages as $index => $package ) {
            if ( ! is_array( $package ) ) {
                continue;
            }

            $methods   = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
            $chosen_id = isset( $chosen[ $index ] ) ? $chosen[ $index ] : '';

            foreach ( $methods as $method ) {
                if ( ! $method instanceof \WC_Shipping_Rate ) {
                    continue;
                }

                $name = $method->get_label();
                if ( is_string( $name ) && '' !== $name && ! in_array( $name, $available_names, true ) ) {
                    $available_names[] = $name;
                }

                if ( '' !== $chosen_id && $method->id === $chosen_id ) {
                    $has_selected = true;
                    if ( is_string( $name ) && '' !== $name && ! in_array( $name, $selected_names, true ) ) {
                        $selected_names[] = $name;
                    }
                }
            }
        }

        // phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Woo's strings, so its translation still applies.
        // The selected method(s), else the only available rate, else plain Shipping.
        if ( array() !== $selected_names ) {
            $label = implode( ', ', $selected_names );
        } elseif ( 1 === count( $available_names ) ) {
            $label = $available_names[0];
        } else {
            $label = __( 'Shipping', 'woocommerce' );
        }

        if ( $has_selected ) {
            $amount = (float) WC()->cart->get_shipping_total();
            if ( WC()->cart->display_prices_including_tax() ) {
                $amount += (float) WC()->cart->get_shipping_tax();
            }

            if ( 0.0 === round( $amount, wc_get_price_decimals() ) ) {
                $value = '<strong>' . esc_html__( 'Free', 'woocommerce' ) . '</strong>';
            } else {
                $value = wc_price( $amount );
            }
        } elseif ( WC()->customer && WC()->customer->has_calculated_shipping() ) {
            $value = esc_html__( 'No available delivery option', 'woocommerce' );
        } else {
            $value = esc_html__( 'Enter address to calculate', 'woocommerce' );
        }
        // phpcs:enable WordPress.WP.I18n.TextDomainMismatch
        ?>
        <tr class="yaycheckout-order-summary-breakdown__shipping">
            <th><?php echo esc_html( $label ); ?></th>
            <td><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above or wc_price() HTML. ?></td>
        </tr>
        <?php
    }
}
