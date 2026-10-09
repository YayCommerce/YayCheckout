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

    public const ORDER_TOTALS_CLASS = 'yaycheckout-order-summary-totals';

    /** Layout values the Shipping Methods block accepts; an unknown attribute falls back to default. */
    public const SHIPPING_LAYOUTS = [ 'default', 'check', 'boxed', 'columns', 'stacked', 'divided' ];

    /** Layout slot => [ selector WooCommerce's checkout.js replaces, method building its HTML ]. */
    private const FRAGMENTS = [
        'payment'          => [ '.woocommerce-checkout-payment', 'payment_methods_html' ],
        'shipping_methods' => [ '.' . self::SHIPPING_RATES_CLASS, 'shipping_rates_html' ],
    ];

    /** Order Summary child block => [ selector checkout.js replaces, method building its HTML ]; found by block name, not slot. */
    private const BLOCK_FRAGMENTS = [
        'yaycheckout/order-summary-items'  => [ '.' . self::ORDER_ITEMS_CLASS, 'order_items_html' ],
        'yaycheckout/order-summary-totals' => [ '.' . self::ORDER_TOTALS_CLASS, 'order_totals_html' ],
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
     * filter runs. A layout with Items or Totals discards it, so swap it for an empty template
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

    /** Swaps the fragments of this layout's sections for this layout's HTML, and refreshes Place order's total. */
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

        foreach ( array_merge( array_values( array_intersect_key( self::FRAGMENTS, $rendered ) ), $summary ) as [ $selector, $build ] ) {
            $html = self::$build();

            if ( '' !== $html ) {
                $fragments[ $selector ] = $html;
            }
        }

        $button = isset( $rendered['place_order'] ) ? self::place_order_button_html( $layout->content ) : '';

        if ( '' !== $button ) {
            $fragments['#place_order'] = $button;
        }

        return $fragments;
    }

    /**
     * The BLOCK_FRAGMENTS entries whose block is in the layout.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function summary_block_fragments( string $markup ): array {
        $names = array_column( LayoutMarkupReader::flatten( parse_blocks( $markup ) ), 'blockName' );

        return array_values( array_intersect_key( self::BLOCK_FRAGMENTS, array_flip( array_filter( $names, 'is_string' ) ) ) );
    }

    /** The Place order button alone (render.php prints only it during AJAX), when it shows the total. */
    private static function place_order_button_html( string $markup ): string {
        foreach ( LayoutMarkupReader::flatten( parse_blocks( $markup ) ) as $block ) {
            if ( 'yaycheckout/place-order' === $block['blockName'] ) {
                return empty( $block['attrs']['showTotal'] ) ? '' : render_block( $block );
            }
        }

        return '';
    }

    public static function print_shipping_rates(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rate inputs match Woo's checkout contract; labels are its filtered HTML.
        echo self::shipping_rates_html();
    }

    /**
     * The Shipping Methods layout from block attributes, or default when missing or unknown.
     *
     * @param array $attributes Block attributes.
     */
    public static function shipping_layout( array $attributes ): string {
        $layout = isset( $attributes['layout'] ) && is_string( $attributes['layout'] ) ? $attributes['layout'] : '';

        return in_array( $layout, self::SHIPPING_LAYOUTS, true ) ? $layout : 'default';
    }

    /**
     * Inline style attribute value of --yaycheckout-shipping-* vars from the block's style object.
     * Empty when no Methods styles are set. Mirrors packages/blocks/.../style-paths.ts.
     *
     * @param array $attributes Block attributes.
     */
    public static function shipping_methods_style_attribute( array $attributes ): string {
        $style   = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : [];
        $spacing = isset( $style['spacing'] ) && is_array( $style['spacing'] ) ? $style['spacing'] : [];
        $border  = isset( $style['border'] ) && is_array( $style['border'] ) ? $style['border'] : [];
        $color   = isset( $style['color'] ) && is_array( $style['color'] ) ? $style['color'] : [];
        $yay     = isset( $style['yaycheckout'] ) && is_array( $style['yaycheckout'] ) ? $style['yaycheckout'] : [];
        $decls   = [];

        $gap = self::shipping_css_gap( $spacing['blockGap'] ?? null );
        if ( '' !== $gap ) {
            $decls[] = '--yaycheckout-shipping-gap:' . $gap;
        }

        $radius = self::shipping_css_length( $border['radius'] ?? null );
        if ( '' !== $radius ) {
            $decls[] = '--yaycheckout-shipping-radius:' . $radius;
        }

        $bg = self::shipping_css_color( $color['background'] ?? null );
        if ( '' !== $bg ) {
            $decls[] = '--yaycheckout-shipping-bg:' . $bg;
        }

        $bg_selected = self::shipping_css_color( $yay['backgroundSelected'] ?? null );
        if ( '' !== $bg_selected ) {
            $decls[] = '--yaycheckout-shipping-bg-selected:' . $bg_selected;
        }

        $bg_hover = self::shipping_css_color( $yay['backgroundHover'] ?? null );
        if ( '' !== $bg_hover ) {
            $decls[] = '--yaycheckout-shipping-bg-hover:' . $bg_hover;
        }

        return implode( ';', $decls );
    }

    /**
     * @param mixed $gap Block style spacing.blockGap.
     */
    private static function shipping_css_gap( $gap ): string {
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

        return self::shipping_css_safe_token( $raw );
    }

    /**
     * @param mixed $value Border radius string, number, or corner object (ignored).
     */
    private static function shipping_css_length( $value ): string {
        if ( is_numeric( $value ) ) {
            return absint( $value ) . 'px';
        }

        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }

        return self::shipping_css_safe_token( $value );
    }

    /**
     * @param mixed $value A CSS colour string.
     */
    private static function shipping_css_color( $value ): string {
        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }

        $hex = sanitize_hex_color( $value );
        if ( $hex ) {
            return $hex;
        }

        return self::shipping_css_safe_token( $value );
    }

    /** Rejects values that could break out of a CSS custom-property declaration. */
    private static function shipping_css_safe_token( string $value ): string {
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
     * Review-order shipping hooks fire around the list, as review-order.php does, since Totals
     * leaves the rates to this block. Plugins on those hooks print table rows, so each hook's
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

    /** An Items or Totals block: its wrapper and the table fragments() refreshes. */
    public static function print_summary_block( \WP_Block $block ): void {
        if ( ! isset( self::BLOCK_FRAGMENTS[ $block->name ] ) ) {
            return;
        }

        $build = self::BLOCK_FRAGMENTS[ $block->name ][1];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it.
        echo '<div ' . get_block_wrapper_attributes() . '>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same HTML Woo prints for review-order rows.
        echo self::$build();
        echo '</div>';
    }

    /**
     * The thead and tbody of Woo's checkout/review-order.php, without its table class so
     * Woo's own review-order fragment cannot replace it with the whole table.
     */
    public static function order_items_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Woo's review-order hooks, filters and strings, so its translation still applies.
        ob_start();
        ?>
<table class="shop_table <?php echo esc_attr( self::ORDER_ITEMS_CLASS ); ?>">
    <thead>
        <tr>
            <th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        do_action( 'woocommerce_review_order_before_cart_contents' );

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

            if ( ! $product instanceof \WC_Product || ! $product->exists() || $cart_item['quantity'] <= 0 || ! $visible ) {
                continue;
            }
            ?>
        <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
            <td class="product-name">
                <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
                <?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's filtered HTML. ?>
                <?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo escapes item data. ?>
            </td>
            <td class="product-total">
                <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo's price HTML. ?>
            </td>
        </tr>
            <?php
        }

        do_action( 'woocommerce_review_order_after_cart_contents' );
        ?>
    </tbody>
</table>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch

        return (string) ob_get_clean();
    }

    /**
     * The tfoot rows of Woo's checkout/review-order.php, as the body of their own table, without
     * the shipping rates: the Shipping Methods block offers those, and Total still counts them.
     */
    public static function order_totals_html(): string {
        if ( ! WC()->cart instanceof \WC_Cart ) {
            return '';
        }

        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- Woo's review-order hooks and strings, so its translation still applies.
        ob_start();
        ?>
<table class="shop_table <?php echo esc_attr( self::ORDER_TOTALS_CLASS ); ?>">
    <tbody>
        <tr class="cart-subtotal">
            <th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
            <td><?php wc_cart_totals_subtotal_html(); ?></td>
        </tr>
        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
        <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
            <td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
        </tr>
        <?php endforeach; ?>
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
        <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
        <tr class="order-total">
            <th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
            <td><?php wc_cart_totals_order_total_html(); ?></td>
        </tr>
        <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
    </tbody>
</table>
        <?php
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch

        return (string) ob_get_clean();
    }
}
