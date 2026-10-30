<?php
namespace YayCheckout\Renderer;

use YayCheckout\Helpers\CheckoutSetupHelper;
use YayCheckout\Helpers\KsesHelper;
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

    public const SAVINGS_NOTE_CLASS = 'yaycheckout-savings-note';

    /** The Coupon form's applied-coupon list, refreshed by fragments(). */
    public const COUPON_CHIPS_CLASS = 'yaycheckout-coupon-form__chips';

    /** Posted by assets/js/checkout-coupon-form.js with apply_coupon, so the cart page's own apply keeps Woo's notice. */
    public const COUPON_FORM_FIELD = 'yaycheckout_coupon_form';

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
        'yaycheckout/savings-note'            => [ '.' . self::SAVINGS_NOTE_CLASS, 'savings_note_html' ],
    ];

    /** Woo's update_order_review fragment for its whole review-order table. */
    private const REVIEW_ORDER_TABLE = '.woocommerce-checkout-review-order-table';

    protected function __construct() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's template path filter.
        add_filter( 'wc_get_template', [ $this, 'skip_review_order_table' ], 10, 2 );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's template actions.
        add_action( 'woocommerce_before_template_part', [ WcHooks::class, 'mute_review_table' ] );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's template actions.
        add_action( 'woocommerce_after_template_part', [ WcHooks::class, 'unmute_review_table' ] );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's coupon message filter.
        add_filter( 'woocommerce_coupon_message', [ $this, 'drop_applied_message' ], PHP_INT_MAX, 2 );
        // After WC_Cart recalculates totals on the same hook (priority 20), so the chips carry the new amounts.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's coupon action.
        add_action( 'woocommerce_applied_coupon', [ $this, 'print_applied_chips' ], 30 );
    }

    /** Whether this is the Coupon form's own apply_coupon request: the cart page posts to the same endpoint without the field. */
    private static function is_coupon_form_request(): bool {
        // Only the Coupon form posts the field, and it renders only under a layout.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Woo's apply_coupon checks its nonce first; this only reads a flag.
        return isset( $_POST[ self::COUPON_FORM_FIELD ] ) && doing_action( 'wc_ajax_apply_coupon' );
    }

    /**
     * The Coupon form's chip replaces Woo's "Coupon code applied successfully." notice, so an empty
     * message stops Woo adding it; other notices in the apply_coupon response still print under the field.
     * Only for the Coupon form's own request: the cart page posts to the same endpoint.
     */
    public function drop_applied_message( $msg, $msg_code ) {
        if ( \WC_Coupon::WC_COUPON_SUCCESS !== (int) $msg_code || ! self::is_coupon_form_request() ) {
            return $msg;
        }

        return '';
    }

    /**
     * Prints the fresh chips into the Coupon form's apply_coupon response, ahead of the notices Woo prints after
     * this hook, so checkout-coupon-form.js shows the chip without waiting for update_order_review.
     * The hook fires only once the coupon is applied.
     */
    public function print_applied_chips(): void {
        if ( self::is_coupon_form_request() ) {
            echo wp_kses( self::coupon_chips_html(), KsesHelper::storefront_html() );
        }
    }

    /**
     * During update_order_review, Woo renders its whole review-order table before the fragments
     * filter runs. A layout with Items, Order Summary Breakdown or Order Summary Total discards it, so swap it for an empty
     * template and its review-order hooks fire once, in our tables. A review-order hook a WooCommerce Hook block prints is
     * kept out of the table by WcHooks::mute_review_table() instead, so an Order Summary without blocks keeps its table.
     */
    public function skip_review_order_table( $template, $template_name ) {
        if ( 'checkout/review-order.php' !== $template_name || ! doing_action( 'wc_ajax_update_order_review' ) ) {
            return $template;
        }

        $layout = CheckoutPage::get_instance()->layout();

        if ( null === $layout || ! self::replaces_review_table( $layout->content ) ) {
            return $template;
        }

        return YAYCHECKOUT_PLUGIN_DIR . 'templates/review-order-skipped.php';
    }

    /** Whether the layout prints its own review-order hooks, so WooCommerce's review table would only fire them again. */
    private static function replaces_review_table( string $markup ): bool {
        return ! empty( self::summary_block_fragments( $markup ) );
    }

    /** Swaps the fragments of this layout's sections for this layout's HTML. */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout ) {
            return $fragments;
        }

        $rendered = array_flip( LayoutMarkupReader::slots( $layout->content ) );
        $summary  = self::summary_block_fragments( $layout->content );

        if ( self::replaces_review_table( $layout->content ) ) {
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

        $html = array_merge( $html, WcHooks::fragments() );

        // Matches nothing on a layout without a Coupon form.
        $html[ '.' . self::COUPON_CHIPS_CLASS ] = self::coupon_chips_html();

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
    public static function css_color( $value ): string {
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

    /**
     * Shipping methods is empty when the cart needs no shipping; the "enter your address" and "no options" messages count as content.
     * A store manager still sees it when shipping lacks a method, to read the setup hint.
     */
    public static function shipping_empty(): bool {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return true;
        }

        if ( self::shipping_setup_hinted() ) {
            return false;
        }

        return ! ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() );
    }

    /** A store manager with a cart that would ship, on a store with no shipping method to offer. */
    private static function shipping_setup_hinted(): bool {
        return current_user_can( 'manage_woocommerce' ) && CheckoutSetupHelper::shipping_needs_setup() && CheckoutSetupHelper::cart_has_shippable_item();
    }

    /** An info notice only store managers get: why the block has nothing to offer, with a link to fix it. */
    private static function print_setup_hint( string $message, string $url, string $link_text ): void {
        wc_print_notice(
            '<strong>' . esc_html__( 'Only store managers see this.', 'yaycheckout' ) . '</strong> ' . esc_html( $message ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( $link_text ) . '</a>',
            'notice'
        );
    }

    /** Payment is empty when the order needs no payment; the "no available payment methods" notice counts as content. */
    public static function payment_empty(): bool {
        return ! WC()->cart instanceof \WC_Cart || ! WC()->cart->needs_payment();
    }

    /** BlockEmptiness's markers for a fragment root, so the visibility script reads emptiness from the swapped HTML. */
    private static function live_attributes( bool $is_empty ): string {
        return ' ' . BlockEmptiness::LIVE_ATTR . '="1"' . ( $is_empty ? ' ' . BlockEmptiness::EMPTY_ATTR . '="1"' : '' );
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

        echo '<div class="' . esc_attr( self::SHIPPING_RATES_CLASS ) . '" ' . esc_attr( BlockEmptiness::LIVE_ATTR ) . '="1"' . ( $empty ? ' ' . esc_attr( BlockEmptiness::EMPTY_ATTR ) . '="1"' : '' ) . '>';

        if ( self::shipping_setup_hinted() ) {
            self::print_setup_hint(
                __( 'No shipping zone or method is set up, so shoppers check out without choosing shipping.', 'yaycheckout' ),
                CheckoutSetupHelper::shipping_settings_url(),
                __( 'Set up shipping', 'yaycheckout' )
            );
        } elseif ( ! $empty ) {
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

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's shipping hooks.
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
                wc_print_notice( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'yaycheckout' ) ), 'notice' );
            } else {
                wc_print_notice( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'yaycheckout' ) ), 'notice' );
            }

            if ( 1 < $package_count ) {
                self::print_shipping_package_details( $package );
            }

            echo '</div>';
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }

    /**
     * Radios Woo's checkout.js listens for. The label is the method name and the price is its own
     * element (shipping_rate_label_parts()): core concatenates them, which leaves no node for a card
     * layout to place.
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
            $parts    = self::shipping_rate_label_parts( $method );
            $name     = $parts['name'];
            $price    = $parts['price'];

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
            echo wp_kses( $name, KsesHelper::storefront_html() );
            echo '</span></span>';

            if ( '' !== $price ) {
                echo '<span class="yaycheckout-shipping-methods__price">' . wp_kses( $price, KsesHelper::storefront_html() ) . '</span>';
            }

            echo '</label>';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's per-rate hook; plugins print pickup and delivery fields here.
            do_action( 'woocommerce_after_shipping_rate', $method, $index );
            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * A rate's name and price, split from the label wc_cart_totals_shipping_method_label() builds.
     * woocommerce_cart_shipping_method_full_label gets core's "Name: price", because plugins rebuild
     * or extend it: a currency switcher returns "Name: price (~converted)". The result is split at
     * ": price", so the price keeps its own element along with whatever a plugin appended after it.
     * When the split finds no price, the whole label is the name and the price is left out, so a
     * label that carries its own price never prints it twice.
     *
     * @return array{name: string, price: string}
     */
    private static function shipping_rate_label_parts( \WC_Shipping_Rate $method ): array {
        $name  = $method->get_label();
        $price = self::shipping_rate_price_html( $method );
        $label = '' === $price ? $name : $name . ': ' . $price;

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's rate-label filter.
        $filtered = apply_filters( 'woocommerce_cart_shipping_method_full_label', $label, $method );
        if ( ! is_string( $filtered ) ) {
            return [
                'name'  => $name,
                'price' => $price,
            ];
        }

        $separator = ': ' . $price;
        $at        = '' === $price ? false : strpos( $filtered, $separator );
        if ( false === $at ) {
            return [
                'name'  => $filtered,
                'price' => '',
            ];
        }

        return [
            'name'  => substr( $filtered, 0, $at ),
            'price' => $price . substr( $filtered, $at + strlen( $separator ) ),
        ];
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

    /** Output from a review-order shipping hook, unless a WooCommerce Hook block prints it instead. */
    private static function print_shipping_extension_rows( string $hook ): void {
        ob_start();
        WcHooks::in_place( $hook );

        echo self::wrap_extension_html( (string) ob_get_clean(), 'yaycheckout-shipping-methods__extensions' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML plugins print on Woo's hooks.
    }

    /**
     * What a plugin printed on a WooCommerce hook, in a wrapper of class $css_class, or '' when it printed nothing.
     * Plugins usually print <tr> rows, which need a table; anything else (div, notice) goes in a plain
     * wrapper so it is not forced into <tbody>.
     */
    public static function wrap_extension_html( string $html, string $css_class ): string {
        if ( '' === trim( $html ) ) {
            return '';
        }

        if ( 1 === preg_match( '/^\s*<tr[\s>]/i', $html ) ) {
            return '<table class="shop_table ' . esc_attr( $css_class ) . '"><tbody>' . $html . '</tbody></table>';
        }

        return '<div class="' . esc_attr( $css_class ) . '">' . $html . '</div>';
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
            WcHooks::in_place( 'woocommerce_review_order_before_payment' );
        }
        ?>
<div id="payment" class="woocommerce-checkout-payment" <?php echo esc_attr( BlockEmptiness::LIVE_ATTR ); ?>="1"<?php echo $empty ? ' ' . esc_attr( BlockEmptiness::EMPTY_ATTR ) . '="1"' : ''; ?>>
        <?php if ( ! $empty && ! empty( $available_gateways ) ) : ?>
    <ul class="wc_payment_methods payment_methods methods" aria-label="<?php esc_attr_e( 'Payment methods', 'yaycheckout' ); ?>">
            <?php
            foreach ( $available_gateways as $gateway ) {
                wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
            }
            ?>
    </ul>
            <?php
        elseif ( ! $empty ) :
            // Outside the list, unlike Woo's payment.php, so the row styles never draw the notice as a selected method.
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

            if ( current_user_can( 'manage_woocommerce' ) && CheckoutSetupHelper::payment_needs_setup() ) {
                self::print_setup_hint(
                    __( 'No payment method is enabled, so shoppers cannot place an order.', 'yaycheckout' ),
                    CheckoutSetupHelper::payment_settings_url(),
                    __( 'Set up payments', 'yaycheckout' )
                );
            }
        endif;
        ?>
</div>
        <?php
        if ( ! wp_doing_ajax() ) {
            WcHooks::in_place( 'woocommerce_review_order_after_payment' );
        }

        return (string) ob_get_clean();
    }

    /**
     * A Savings note block: watchable wrapper and the fragment root fragments() refreshes.
     *
     * @param \WP_Block $block Block instance.
     */
    public static function print_savings_note( \WP_Block $block ): void {
        $empty = CheckoutSavingsNote::is_zero();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
        echo '<div ' . BlockEmptiness::wrapper( [], $block->attributes, $empty ) . '>';
        echo wp_kses( self::savings_note_html( $block->attributes ), KsesHelper::storefront_html() );
        echo '</div>';
    }

    /**
     * The Savings note sentence for first paint and update_order_review. Empty of text while
     * savings are zero so a "Block is empty" condition can hide a block after the fragment swaps.
     *
     * @param array $attributes Block attributes.
     */
    public static function savings_note_html( array $attributes = [] ): string {
        $empty = CheckoutSavingsNote::is_zero();
        $inner = $empty ? '' : CheckoutSavingsNote::sentence_html( $attributes );

        return '<p class="' . esc_attr( self::SAVINGS_NOTE_CLASS ) . '"' . self::live_attributes( $empty ) . '>' . $inner . '</p>';
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

            $style_vars = self::order_items_style_vars( $block->attributes );
            if ( '' !== $style_vars ) {
                $wrapper['style'] = $style_vars;
            }

            $stepper = 'stepper' === self::order_items_options( $block->attributes )['quantity_display'];

            if ( $stepper ) {
                $wrapper['data-yaycheckout-cart-nonce'] = wp_create_nonce( CartItemQuantity::NONCE_ACTION );
                $wrapper['data-error-text']             = CartItemQuantity::retry_message();
            }
        } else {
            // Breakdown and Total: their Label and Value styles, on the wrapper for the same reason.
            $class = SummaryPartStyles::wrapper_class( $block->attributes, $block->name, ltrim( self::BLOCK_FRAGMENTS[ $block->name ][0], '.' ) );

            if ( '' !== $class ) {
                $wrapper['class'] = $class;
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
     * Inline --yaycheckout-badge-*, --yaycheckout-stepper-* and --yaycheckout-thumbnail-* vars from the
     * Items block's badge, stepper and thumbnail attributes; empty when none are set. On the wrapper, which checkout.js keeps across
     * refreshes. itemsStyleVars() in order-summary-items/edit.tsx mirrors it.
     *
     * @param array $attributes Block attributes.
     */
    private static function order_items_style_vars( array $attributes ): string {
        $decls = [];

        $colors = [
            'badgeBackgroundColor' => '--yaycheckout-badge-bg',
            'badgeTextColor'       => '--yaycheckout-badge-text',
            'stepperBorderColor'   => '--yaycheckout-stepper-border',
        ];
        foreach ( $colors as $key => $var ) {
            $value = self::css_color( $attributes[ $key ] ?? null );
            if ( '' !== $value ) {
                $decls[] = $var . ':' . $value;
            }
        }

        // Attribute => [ var, min px, max px ].
        $sizes = [
            'badgeSize'       => [ '--yaycheckout-badge-size', 16, 32 ],
            'stepperSize'     => [ '--yaycheckout-stepper-size', 20, 40 ],
            'thumbnailSize'   => [ '--yaycheckout-thumbnail-size', 40, 96 ],
            'thumbnailRadius' => [ '--yaycheckout-thumbnail-radius', 0, 48 ],
        ];
        foreach ( $sizes as $key => list( $var, $min, $max ) ) {
            if ( isset( $attributes[ $key ] ) && is_numeric( $attributes[ $key ] ) ) {
                $decls[] = $var . ':' . max( $min, min( $max, (int) $attributes[ $key ] ) ) . 'px';
            }
        }

        // Square, the default, is style.css's fallback; original keeps the image's own proportions.
        $ratios = [
            'portrait'  => '3 / 4',
            'landscape' => '4 / 3',
            'original'  => 'auto',
        ];
        $ratio  = $attributes['thumbnailRatio'] ?? null;
        if ( is_string( $ratio ) && isset( $ratios[ $ratio ] ) ) {
            $decls[] = '--yaycheckout-thumbnail-ratio:' . $ratios[ $ratio ];
        }

        return implode( ';', $decls );
    }

    /**
     * The Items block's attributes with their defaults. During AJAX they come from the parsed
     * layout, where an untouched attribute is missing, so each default matches block.json's.
     *
     * @return array{show_labels: bool, product_label: string, subtotal_label: string, show_thumbnails: bool, item_data: string, show_sku: bool, show_unit_price: bool, show_short_description: bool, quantity_display: string, show_badge: bool, link_image: bool, link_name: bool, stepper_style: string}
     */
    public static function order_items_options( array $attributes ): array {
        // The first choice is the default.
        $choice = static function ( string $name, array $choices ) use ( $attributes ): string {
            return isset( $attributes[ $name ] ) && in_array( $attributes[ $name ], $choices, true ) ? $attributes[ $name ] : $choices[0];
        };

        return [
            'show_labels'            => self::bool_attr( $attributes, 'showLabels', true ),
            'product_label'          => LayoutMarkupReader::plain_text( $attributes, 'productLabel', __( 'Product', 'yaycheckout' ) ),
            'subtotal_label'         => LayoutMarkupReader::plain_text( $attributes, 'subtotalLabel', __( 'Subtotal', 'yaycheckout' ) ),
            'show_thumbnails'        => self::bool_attr( $attributes, 'showThumbnails', true ),
            'item_data'              => $choice( 'itemData', [ 'inline', 'list', 'hidden' ] ),
            'show_sku'               => self::bool_attr( $attributes, 'showSku', false ),
            'show_unit_price'        => self::bool_attr( $attributes, 'showUnitPrice', false ),
            'show_short_description' => self::bool_attr( $attributes, 'showShortDescription', false ),
            'quantity_display'       => $choice( 'quantityDisplay', [ 'inline', 'stepper', 'hidden' ] ),
            'show_badge'             => self::bool_attr( $attributes, 'showBadge', false ),
            'link_image'             => self::bool_attr( $attributes, 'linkImage', false ),
            'link_name'              => self::bool_attr( $attributes, 'linkName', false ),
            'stepper_style'          => $choice( 'stepperStyle', [ 'boxed', 'round' ] ),
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
        // A badge sits on the thumbnail, so it needs one. It is independent of quantity_display and hides nothing.
        $badge        = $options['show_badge'] && $options['show_thumbnails'];
        $stepper_mode = 'stepper' === $options['quantity_display'];
        $root         = self::ORDER_ITEMS_CLASS . ( 'inline' === $options['item_data'] ? ' ' . self::ORDER_ITEMS_CLASS . '--item-data-inline' : '' );
        $root        .= $stepper_mode && 'round' === $options['stepper_style'] ? ' ' . self::ORDER_ITEMS_CLASS . '--stepper-round' : '';

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

            // A product with one allowed quantity gets no stepper; its quantity shows inline instead.
            $stepper = $stepper_mode
                && ! $product->is_sold_individually()
                && $product->get_min_purchase_quantity() !== $product->get_max_purchase_quantity();

            // Links open in a new tab, so the shopper keeps the checkout and what they typed into it.
            $permalink  = $options['link_image'] || $options['link_name'] ? self::product_permalink( $product, $cart_item, $cart_item_key ) : '';
            $link_name  = $options['link_name'] && '' !== $permalink;
            $link_image = $options['link_image'] && '' !== $permalink;
            $link_open  = $link_name || $link_image ? '<a class="' . esc_attr( self::ORDER_ITEMS_CLASS . '__link' ) . '" href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener"' : '';
            ?>
    <div class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) . ' ' . self::ORDER_ITEMS_CLASS . '__item' ); ?>">
            <?php if ( $options['show_thumbnails'] ) : ?>
        <div class="product-thumbnail">
                <?php
                $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image(), $cart_item, $cart_item_key );

                if ( $link_image ) {
                    // With the name linked too, the image link is skipped by keyboard and screen readers, so each product is one stop.
                    $thumbnail = $link_open . ( $link_name ? ' tabindex="-1" aria-hidden="true"' : '' ) . '>' . $thumbnail . '</a>';
                }

                echo wp_kses( $thumbnail, KsesHelper::storefront_html() );
                ?>
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
            <?php // Linked before the filter, as cart/cart.php does, so extensions get the input they get on the cart page. ?>
            <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $link_name ? $link_open . '>' . $product->get_name() . '</a>' : $product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
            <?php // Hidden shows no quantity here, and the stepper's input already shows it; the filter still runs, as extensions (Germanized's units line) print their item info through it. ?>
            <?php $inline_quantity = 'hidden' !== $options['quantity_display'] && ! $stepper ? ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>' : ''; ?>
            <?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', $inline_quantity, $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's filtered HTML. ?>
            <?php
            if ( 'hidden' !== $options['item_data'] ) {
                echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo escapes item data.
            }

            self::print_item_details( $options, $product, $cart_item, $cart_item_key );

            // A row of its own, so the stepper never flows onto the name's line when no detail line sits between them.
            if ( $stepper_mode ) {
                echo '<div class="' . esc_attr( self::ORDER_ITEMS_CLASS . '__controls' ) . '">';

                if ( $stepper ) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart quantity filter, so an extension that locks an item's quantity locks it here.
                    echo apply_filters( 'woocommerce_cart_item_quantity', self::stepper_html( $product, $cart_item, $cart_item_key ), $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in stepper_html(), then Woo's filtered HTML.
                }

                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart remove-link filter.
                echo apply_filters( 'woocommerce_cart_item_remove_link', self::remove_link_html( $product, $cart_item_key ), $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in remove_link_html(), then Woo's filtered HTML.
                echo '</div>';
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

    /** The product page an item links to, or '' when the product has none a shopper may see, as cart/cart.php works it out. */
    private static function product_permalink( \WC_Product $product, array $cart_item, string $cart_item_key ): string {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart permalink filter.
        return (string) apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
    }

    /** The SKU, unit price and short description under an item's name, each when its option is on. */
    private static function print_item_details( array $options, \WC_Product $product, array $cart_item, string $cart_item_key ): void {
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's cart filter.
        $sku = $options['show_sku'] ? $product->get_sku() : '';

        if ( '' !== $sku ) {
            echo '<div class="product-sku">' . esc_html__( 'SKU:', 'yaycheckout' ) . ' ' . esc_html( $sku ) . '</div>';
        }

        if ( $options['show_unit_price'] ) {
            $price = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $cart_item, $cart_item_key );
            /* translators: %s: product price */
            echo '<div class="product-unit-price">' . wp_kses( sprintf( esc_html__( '%s each', 'yaycheckout' ), $price ), KsesHelper::storefront_html() ) . '</div>';
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

    /** Fires a cart-contents hook and prints its output, if any, in a wrapper of its own, unless a WooCommerce Hook block prints it instead. */
    private static function print_order_items_hook( string $hook ): void {
        ob_start();
        WcHooks::in_place( $hook );

        echo self::wrap_extension_html( (string) ob_get_clean(), self::ORDER_ITEMS_CLASS . '__hook' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Extensions' own review-order output.
    }

    /** A boolean block attribute, or $fallback when it is missing or not a boolean. */
    private static function bool_attr( array $attributes, string $name, bool $fallback ): bool {
        return isset( $attributes[ $name ] ) && is_bool( $attributes[ $name ] ) ? $attributes[ $name ] : $fallback;
    }

    /** The amount half of Woo's wc_cart_totals_coupon_html() (wc-cart-functions.php), without its [Remove] link: the chip carries that. */
    public static function coupon_amount_html( \WC_Coupon $coupon ): string {
        $amount = WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax );
        $html   = '-' . wc_price( $amount );

        if ( $coupon->get_free_shipping() && empty( $amount ) ) {
            $html = __( 'Free shipping coupon', 'yaycheckout' );
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's filter, so extensions still change the amount.
        return (string) apply_filters( 'woocommerce_coupon_discount_amount_html', $html, $coupon );
    }

    /** The Coupon form's applied coupons as chips; checkout-coupon-form.js runs their ×, and the href serves without JS. */
    public static function coupon_chips_html(): string {
        $html = '<ul class="' . esc_attr( self::COUPON_CHIPS_CLASS ) . '">';

        if ( WC()->cart instanceof \WC_Cart ) {
            $checkout_url = wc_get_checkout_url();

            foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
                $code = (string) $code;
                // translators: %s: coupon code.
                $label = sprintf( __( 'Remove %s coupon', 'yaycheckout' ), $code );
                $html .= '<li class="yaycheckout-coupon-form__chip">'
                    . '<svg class="yaycheckout-coupon-form__chip-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>'
                    . '<strong>' . esc_html( wc_strtoupper( $code ) ) . '</strong>'
                    . '<span aria-hidden="true">·</span>'
                    // One flex item, or the gap would split the minus sign from the price.
                    . '<span class="yaycheckout-coupon-form__chip-amount">' . wp_kses_post( self::coupon_amount_html( $coupon ) ) . '</span>'
                    . '<a role="button" class="yaycheckout-coupon-form__chip-remove"'
                    . ' href="' . esc_url( add_query_arg( 'remove_coupon', rawurlencode( $code ), $checkout_url ) ) . '"'
                    . ' data-coupon="' . esc_attr( $code ) . '"'
                    . ' aria-label="' . esc_attr( $label ) . '">×</a>'
                    . '</li>';
            }
        }

        return $html . '</ul>';
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
            'subtotal_label' => LayoutMarkupReader::plain_text( $attributes, 'subtotalLabel', __( 'Subtotal', 'yaycheckout' ) ),
        ];
    }

    /**
     * The tfoot rows of Woo's checkout/review-order.php, as the body of their own table. Shipping
     * is one read-only row (method name / Free); the Shipping Methods block keeps the radios. The
     * order-total row is the Order Summary Total block's; its hooks stay here, so extensions' rows print once.
     * Coupon rows leave out Woo's [Remove] when the layout has a Coupon form, whose chips carry it.
     *
     * @param array $attributes Order Summary Breakdown block attributes; see order_breakdown_options().
     */
    public static function order_breakdown_html( array $attributes = [] ): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        $options = self::order_breakdown_options( $attributes );
        $layout  = CheckoutPage::get_instance()->layout();
        // Without a Coupon form there are no chips, so the rows keep Woo's [Remove].
        $chips = null !== $layout && has_block( 'yaycheckout/coupon-form', $layout->content );

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Woo's review-order hooks.
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
            <?php if ( $chips ) : ?>
                <?php
                $amount_html = self::coupon_amount_html( $coupon );
                // Woo's filter, given the amount without [Remove], so extensions that add to the row still apply.
                ?>
            <td><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_totals_coupon_html', $amount_html, $coupon, $amount_html ) ); ?></td>
            <?php else : ?>
            <td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
            <?php endif; ?>
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
        <?php WcHooks::in_place( 'woocommerce_review_order_before_order_total' ); ?>
        <?php WcHooks::in_place( 'woocommerce_review_order_after_order_total' ); ?>
    </tbody>
</table>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

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
            'label'              => LayoutMarkupReader::plain_text( $attributes, 'label', __( 'Total', 'yaycheckout' ) ),
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

        $selected_names   = array();
        $selected_methods = array();
        $available_names  = array();
        $has_selected     = false;

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
                    $has_selected       = true;
                    $selected_methods[] = $method;
                    if ( is_string( $name ) && '' !== $name && ! in_array( $name, $selected_names, true ) ) {
                        $selected_names[] = $name;
                    }
                }
            }
        }

        // The selected method(s), else the only available rate, else plain Shipping.
        if ( array() !== $selected_names ) {
            $label = implode( ', ', $selected_names );
        } elseif ( 1 === count( $available_names ) ) {
            $label = $available_names[0];
        } else {
            $label = __( 'Shipping', 'yaycheckout' );
        }

        if ( $has_selected ) {
            $amount = (float) WC()->cart->get_shipping_total();
            if ( WC()->cart->display_prices_including_tax() ) {
                $amount += (float) WC()->cart->get_shipping_tax();
            }

            // One selected rate shows its price as Shipping Methods does, with what the rate-label
            // filter appended (a currency switcher's converted amount); several are summed.
            $rate_price = 1 === count( $selected_methods ) ? self::shipping_rate_label_parts( $selected_methods[0] )['price'] : '';

            if ( 0.0 === round( $amount, wc_get_price_decimals() ) ) {
                $value = '<strong>' . esc_html__( 'Free', 'yaycheckout' ) . '</strong>';
            } elseif ( '' !== $rate_price ) {
                $value = $rate_price;
            } else {
                $value = wc_price( $amount );
            }
        } elseif ( WC()->customer && WC()->customer->has_calculated_shipping() ) {
            $value = esc_html__( 'No available delivery option', 'yaycheckout' );
        } else {
            $value = esc_html__( 'Enter address to calculate', 'yaycheckout' );
        }
        ?>
        <tr class="yaycheckout-order-summary-breakdown__shipping">
            <th><?php echo esc_html( $label ); ?></th>
            <td><?php echo wp_kses( $value, KsesHelper::storefront_html() ); ?></td>
        </tr>
        <?php
    }
}
