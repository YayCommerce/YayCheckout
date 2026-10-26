<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the active layout's pay-order page (Layout::META_PAY_ORDER) the order-pay page. The page itself is our
 * themeless document, which still runs WooCommerce's [woocommerce_checkout], so WC_Shortcode_Checkout::order_pay()
 * keeps every check (order key, pay_for_order, guest email verification, needs payment, stock). Only
 * checkout/form-pay.php, which WooCommerce reaches once the shopper may pay, is ours: it renders the layout's
 * blocks and is the one place the order is set. Leaves WooCommerce untouched when safe mode is on, no layout is
 * active, or the active layout has no pay-order page.
 */
class PayOrderPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

    private const FORM_TEMPLATE = 'checkout/form-pay.php';

    /** @var Layout|null|false false until first resolved. */
    private $layout = false;

    private ?\WC_Order $order = null;

    /** @var \WC_Payment_Gateway[] */
    private array $gateways = [];

    private string $button_text = '';

    private bool $in_form = false;

    private bool $before_payment_fired = false;

    protected function __construct() {
        add_action( 'wp', [ $this, 'maybe_take_over' ] );
    }

    /** The layout to render, its content already the pay-order markup, or null when the renderer stands down. Memoised per request. */
    public function layout(): ?Layout {
        if ( false === $this->layout ) {
            $this->layout = $this->resolve();
        }

        return $this->layout;
    }

    /** True when this request is order-pay and a pay-order layout renders it. Meaningful from `wp` on. */
    public function takes_over(): bool {
        return null !== $this->layout();
    }

    /** The order WooCommerce let the shopper pay, set once form-pay renders; null before, and when WooCommerce refused. */
    public function order(): ?\WC_Order {
        return $this->order;
    }

    /**
     * The gateways WooCommerce offers for this order, as order_pay() passes them to form-pay.php.
     *
     * @return \WC_Payment_Gateway[]
     */
    public function gateways(): array {
        return $this->gateways;
    }

    /** The pay button's label: woocommerce_pay_order_button_text, as order_pay() passes it to form-pay.php. */
    public function button_text(): string {
        return $this->button_text;
    }

    /** True while templates/checkout-form-pay.php renders the layout's blocks. */
    public function in_form(): bool {
        return $this->in_form;
    }

    /**
     * Called by templates/checkout-form-pay.php with the values WooCommerce gives form-pay.php.
     *
     * @param \WC_Payment_Gateway[] $gateways Available gateways.
     */
    public function begin_form( \WC_Order $order, array $gateways, string $button_text ): void {
        $this->order       = $order;
        $this->gateways    = $gateways;
        $this->button_text = $button_text;
        $this->in_form     = true;
    }

    /**
     * Fires woocommerce_pay_order_before_payment once, from the Express Pay block, where the merchant placed it.
     * Gateways print their express buttons on it (Stripe's Express Checkout Element: Link, Amazon Pay, Apple Pay,
     * Google Pay). A pay-order page without Express Pay never fires it, as the checkout without the block shows
     * no express buttons.
     */
    public function fire_before_payment(): void {
        if ( ! $this->in_form || $this->before_payment_fired ) {
            return;
        }

        $this->before_payment_fired = true;

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's form-pay hook (8.2+).
        do_action( 'woocommerce_pay_order_before_payment' );
    }

    /** The order stays set for what prints after the form, such as wp_footer. */
    public function end_form(): void {
        $this->in_form = false;
    }

    public function maybe_take_over(): void {
        if ( ! $this->takes_over() ) {
            return;
        }

        // PHP_INT_MAX: must run after block themes swap in template-canvas.php.
        add_filter( 'template_include', [ $this, 'page_template' ], PHP_INT_MAX );
        add_filter( 'wc_get_template', [ $this, 'filter_template' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], self::SCRIPTS_PRIORITY );
    }

    public function page_template(): string {
        return YAYCHECKOUT_PLUGIN_DIR . 'templates/pay-order-page.php';
    }

    /** Our form-pay.php in place of WooCommerce's (or the theme's) while a layout takes over; every other template as located. */
    public function filter_template( string $template, string $template_name ): string {
        if ( self::FORM_TEMPLATE !== $template_name || null === $this->layout() ) {
            return $template;
        }

        return YAYCHECKOUT_PLUGIN_DIR . 'templates/checkout-form-pay.php';
    }

    /** Base stylesheet and design tokens; wc-checkout stays, its #order_review handlers run on body.woocommerce-order-pay. */
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

    /** The active layout with its content swapped for the pay-order markup, or null. Reads no order: WooCommerce checks it. */
    private function resolve(): ?Layout {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing; WooCommerce checks the key.
        if ( ! is_checkout_pay_page() || ! isset( $_GET['pay_for_order'] ) ) {
            return null;
        }

        if ( ( new SettingsRepository() )->is_safe_mode() ) {
            return null;
        }

        $layout = ( new LayoutRepository() )->active( CheckoutPage::shopper_context() );

        if ( null === $layout || ! $layout->has_pay_order() ) {
            return null;
        }

        // This request's own copy: every reader of $layout->content (the form, LiveValues, enqueue) gets the pay-order page.
        $layout->content = $layout->pay_order_content;

        return $layout;
    }
}
