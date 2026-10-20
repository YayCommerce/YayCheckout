<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the active thank-you layout the order-received page: our template in place of
 * WooCommerce's thankyou.php, printing the layout's blocks then the thank-you hooks
 * gateways rely on. Leaves WooCommerce untouched when safe mode is on, no thank-you
 * layout resolves, or the order key is missing or wrong.
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

    /** The layout to render, or null when the renderer stands down. Memoised per request. */
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

        // Order Details prints WooCommerce's order-details table; it needs the classic shop styles.
        if ( has_block( 'yaycheckout/order-details', $layout->content ) ) {
            wp_enqueue_style( 'woocommerce-general' );
            wp_enqueue_style( 'woocommerce-layout' );
        }

        ( new CheckoutBaseStyles() )->enqueue( true, $layout->design );
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

        $layout = ( new LayoutRepository() )->active_thank_you( CheckoutPage::shopper_context() );

        if ( null === $layout || Layout::SURFACE_THANK_YOU !== $layout->surface ) {
            return;
        }

        $order = $this->validated_order();

        if ( null === $order ) {
            return;
        }

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
