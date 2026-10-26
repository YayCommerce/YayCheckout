<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the active layout's thank-you page (Layout::META_THANK_YOU) the order-received page: our
 * template in place of WooCommerce's thankyou.php, printing its blocks then the thank-you hooks
 * gateways rely on. Leaves WooCommerce untouched when safe mode is on, no layout is active, the
 * active layout has no thank-you page, or the order key is missing or wrong.
 */
class ThankYouPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

    /** @var Layout|null|false false until first resolved. */
    private $layout = false;

    /** @var \WC_Order|null|false false until first resolved. */
    private $order = false;

    protected function __construct() {
        add_action( 'wp', [ $this, 'maybe_take_over' ] );
    }

    /** The layout to render, its content already the thank-you markup, or null when the renderer stands down. Memoised per request. */
    public function layout(): ?Layout {
        if ( false === $this->layout ) {
            $this->resolve();
        }

        return $this->layout instanceof Layout ? $this->layout : null;
    }

    /** The validated order for this order-received request, or null. Memoised with layout(). */
    public function order(): ?\WC_Order {
        if ( false === $this->order ) {
            $this->resolve();
        }

        return $this->order instanceof \WC_Order ? $this->order : null;
    }

    /** True when this request is order-received and a thank-you layout renders it. Meaningful from `wp` on. */
    public function takes_over(): bool {
        return null !== $this->layout() && null !== $this->order();
    }

    public function maybe_take_over(): void {
        if ( ! $this->takes_over() ) {
            return;
        }

        // PHP_INT_MAX: must run after block themes swap in template-canvas.php.
        add_filter( 'template_include', [ $this, 'page_template' ], PHP_INT_MAX );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], self::SCRIPTS_PRIORITY );
    }

    public function page_template(): string {
        return YAYCHECKOUT_PLUGIN_DIR . 'templates/thankyou-page.php';
    }

    /** Base stylesheet and design tokens only: no WooCommerce checkout scripts on thank-you. */
    public function enqueue_scripts(): void {
        $layout = $this->layout();

        if ( null === $layout ) {
            return;
        }

        foreach ( [ 'wc-blocks-style-checkout', 'woocommerce-general', 'woocommerce-blocktheme', 'woocommerce-layout', 'woocommerce-smallscreen' ] as $handle ) {
            wp_dequeue_style( $handle );
        }

        // Order Details / Order Addresses print WooCommerce order templates; they need classic shop styles.
        if ( has_block( 'yaycheckout/order-details', $layout->content ) || has_block( 'yaycheckout/order-customer-details', $layout->content ) ) {
            wp_enqueue_style( 'woocommerce-general' );
            wp_enqueue_style( 'woocommerce-layout' );
        }

        ( new CheckoutBaseStyles() )->enqueue( true, $layout->design );
    }

    /**
     * Runs $print while WooCommerce gettext for the given English source strings returns the merchant's
     * labels. Empty overrides are skipped so the shop's WooCommerce locale stays in force.
     *
     * @param array<string, string> $overrides Source string (as in WooCommerce templates) => replacement.
     * @param callable              $callback  Printer that echoes WooCommerce template output.
     */
    public static function with_woocommerce_label_overrides( array $overrides, callable $callback ): void {
        $overrides = array_filter(
            $overrides,
            static function ( $label ) {
                return is_string( $label ) && '' !== $label;
            }
        );

        if ( [] === $overrides ) {
            $callback();

            return;
        }

        $filter = static function ( $translation, $text, $domain ) use ( $overrides ) {
            if ( 'woocommerce' !== $domain || ! isset( $overrides[ $text ] ) ) {
                return $translation;
            }

            return $overrides[ $text ];
        };

        add_filter( 'gettext', $filter, 10, 3 );
        $callback();
        remove_filter( 'gettext', $filter, 10 );
    }

    /**
     * Drops the order-details heading WooCommerce prints in order-details.php when the block's
     * Show title toggle is off. Leaves $html untouched when that heading is missing.
     */
    public static function without_order_details_title( string $html ): string {
        $stripped = preg_replace(
            '/<h2\b[^>]*\bwoocommerce-order-details__title\b[^>]*>.*?<\/h2>/is',
            '',
            $html,
            1
        );

        return is_string( $stripped ) ? $stripped : $html;
    }

    /**
     * Drops the billing/shipping section WooCommerce appends inside woocommerce_order_details_table(),
     * so Order Addresses can print order-details-customer.php on its own. Leaves $html untouched when
     * that section is missing (theme override, already stripped).
     */
    public static function without_customer_details( string $html ): string {
        $marker = '<section class="woocommerce-customer-details">';
        $start  = strpos( $html, $marker );

        if ( false === $start ) {
            return $html;
        }

        $depth = 0;
        $pos   = $start;
        $len   = strlen( $html );

        while ( $pos < $len ) {
            $open  = stripos( $html, '<section', $pos );
            $close = stripos( $html, '</section>', $pos );

            if ( false === $close ) {
                return $html;
            }

            if ( false !== $open && $open < $close ) {
                ++$depth;
                $pos = $open + 8;
                continue;
            }

            --$depth;
            $pos = $close + strlen( '</section>' );

            if ( 0 === $depth ) {
                return substr( $html, 0, $start ) . ltrim( substr( $html, $pos ) );
            }
        }

        return $html;
    }

    /**
     * Runs $callback with no order actions, so order-details.php prints no Actions row (Pay, Cancel): on the
     * pay-order page the shopper is already paying.
     *
     * @param callable $callback Printer that echoes WooCommerce template output.
     */
    public static function without_order_actions( callable $callback ): void {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filter.
        add_filter( 'woocommerce_my_account_my_orders_actions', '__return_empty_array', PHP_INT_MAX );
        $callback();
        remove_filter( 'woocommerce_my_account_my_orders_actions', '__return_empty_array', PHP_INT_MAX );
    }

    /**
     * Resolves layout and order together. Both null when this is not a valid takeover.
     */
    private function resolve(): void {
        $this->layout = null;
        $this->order  = null;

        if ( ! is_order_received_page() ) {
            return;
        }

        if ( ( new SettingsRepository() )->is_safe_mode() ) {
            return;
        }

        $layout = ( new LayoutRepository() )->active( CheckoutPage::shopper_context() );

        if ( null === $layout || ! $layout->has_thank_you() ) {
            return;
        }

        $order = $this->validated_order();

        if ( null === $order ) {
            return;
        }

        // This request's own copy: every reader of $layout->content (the template, LiveValues, enqueue) gets the thank-you page.
        $layout->content = $layout->thank_you_content;

        $this->layout = $layout;
        $this->order  = $order;
    }

    /**
     * Order id from the order-received endpoint and key from the query, only when they match.
     * A wrong key must never render the custom layout (order details must not leak).
     */
    private function validated_order(): ?\WC_Order {
        $order_id = absint( get_query_var( 'order-received' ) );

        if ( $order_id <= 0 ) {
            return null;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public thank-you URL; key is the credential, cleaned like WooCommerce's thankyou.php.
        $key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
        // phpcs:enable

        if ( '' === $key ) {
            return null;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order ) {
            return null;
        }

        if ( ! hash_equals( $order->get_order_key(), $key ) ) {
            return null;
        }

        return $order;
    }
}
