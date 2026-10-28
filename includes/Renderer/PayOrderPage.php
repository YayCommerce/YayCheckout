<?php
namespace YayCheckout\Renderer;

use YayCheckout\Engine\Admin\LayoutEditorEmbed;
use YayCheckout\Models\Layout;
use YayCheckout\Pro\Renderer\OrderLiveValues;
use YayCheckout\Pro\Repositories\PreviewOrderRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the active layout's pay-order page (Layout::META_PAY_ORDER) the order-pay page. The page itself is our
 * themeless document, which still runs WooCommerce's [woocommerce_checkout], so WC_Shortcode_Checkout::order_pay()
 * keeps every check (order key, pay_for_order, guest email verification, needs payment, stock). Only
 * checkout/form-pay.php, which WooCommerce reaches once the shopper may pay, is ours: it renders the layout's
 * blocks and is the one place the order is set. Leaves WooCommerce untouched when safe mode is on, no layout is
 * active, or the active layout has no pay-order page. A preview link renders its own layout over the sample
 * order (PreviewOrderRepository), and nothing else; that page skips order_pay() (render_preview_form()).
 */
class PayOrderPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

    private const FORM_TEMPLATE = 'checkout/form-pay.php';

    /** @var Layout|null|false false until first resolved. */
    private $layout = false;

    /** The sample order a preview link shows, set by resolve(); null on a shopper's request. */
    private ?\WC_Order $preview_order = null;

    private ?\WC_Order $order = null;

    /** @var \WC_Payment_Gateway[] */
    private array $gateways = [];

    private string $button_text = '';

    private bool $in_form = false;

    private bool $before_payment_fired = false;

    /** @var array<string, array{text: string, empty: bool}>|null The order's live values, read once per request. */
    private $live_values = null;

    protected function __construct() {
        add_action( 'wp', [ $this, 'maybe_take_over' ] );
        add_filter( 'yaycheckout_editor_views', [ $this, 'editor_views' ] );
        add_filter( 'yaycheckout_admin_bar_layout', [ $this, 'admin_bar_layout' ] );
        add_filter( 'yaycheckout_live_values_source', [ $this, 'live_values_source' ] );
    }

    /**
     * Adds the Pay order editor view, kept in Layout::META_PAY_ORDER.
     *
     * @param array<string, string> $views View => meta key.
     * @return array<string, string>
     */
    public function editor_views( $views ): array {
        $views = is_array( $views ) ? $views : [];

        $views[ LayoutEditorEmbed::VIEW_PAY_ORDER ] = Layout::META_PAY_ORDER;

        return $views;
    }

    /**
     * The Edit Layout item on a page this renders opens the layout's Pay order view.
     *
     * @param mixed $rendered
     * @return mixed
     */
    public function admin_bar_layout( $rendered ) {
        if ( null !== $rendered || ! $this->takes_over() ) {
            return $rendered;
        }

        return [
            'layout' => $this->layout(),
            'view'   => LayoutEditorEmbed::VIEW_PAY_ORDER,
        ];
    }

    /**
     * Once WooCommerce lets the shopper pay, the live value spans read the order: its totals, overview and address fields.
     *
     * @param mixed $source
     * @return mixed
     */
    public function live_values_source( $source ) {
        $order = $this->order;

        if ( null !== $source || ! $this->takes_over() || null === $order ) {
            return $source;
        }

        if ( null === $this->live_values ) {
            $this->live_values = OrderLiveValues::values( $order );
        }

        return [
            'layout' => $this->layout(),
            'values' => $this->live_values,
            'field'  => static function ( string $field ) use ( $order ): ?array {
                return OrderLiveValues::field( $order, $field );
            },
        ];
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

    /** True when a preview link renders the sample order. Meaningful from `wp` on. */
    public function is_preview(): bool {
        return null !== $this->layout() && null !== $this->preview_order;
    }

    /**
     * The preview's form, in place of [woocommerce_checkout]: order_pay() would warn the merchant they are paying
     * a guest order, check the sample products' stock, and save the sample's empty address to their customer.
     * Gives form-pay.php what order_pay() gives it; the button is disabled and PreviewRequest refuses the POST.
     */
    public function render_preview_form(): void {
        if ( ! $this->is_preview() ) {
            return;
        }

        wc_print_notices();

        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        WC()->payment_gateways()->set_current_gateway( $gateways );

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's filter and string, as order_pay() applies them.
        $button_text = apply_filters( 'woocommerce_pay_order_button_text', __( 'Pay for order', 'woocommerce' ) );

        wc_get_template(
            self::FORM_TEMPLATE,
            [
                'order'              => $this->preview_order,
                'available_gateways' => $gateways,
                'order_button_text'  => $button_text,
            ]
        );
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
     * no express buttons. A preview prints none: a wallet would pay the sample order.
     */
    public function fire_before_payment(): void {
        if ( ! $this->in_form || $this->before_payment_fired || null !== $this->preview_order ) {
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

    /**
     * The layout with its content swapped for the pay-order markup, or null: a preview link's layout over the
     * sample order only, else the active one. Reads no shopper's order: WooCommerce checks it.
     */
    private function resolve(): ?Layout {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing; WooCommerce checks the key.
        if ( ! is_checkout_pay_page() || ! isset( $_GET['pay_for_order'] ) ) {
            return null;
        }

        $layout = PreviewRequest::preview_or_active_layout();

        if ( null === $layout || ! $layout->has_pay_order() ) {
            return null;
        }

        if ( null !== PreviewRequest::get_instance()->layout_id() ) {
            $order = $this->sample_order_in_url();

            // A preview link shows only the sample order, so a token can't put a layout over a shopper's order.
            if ( null === $order ) {
                return null;
            }

            $this->preview_order = $order;
        }

        // This request's own copy: every reader of $layout->content (the form, LiveValues, enqueue) gets the pay-order page.
        $layout->content = $layout->pay_order_content;

        return $layout;
    }

    /** The sample order when the URL names it with its key, as order_pay() would check; else null. */
    private function sample_order_in_url(): ?\WC_Order {
        $order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The key is the credential, cleaned like order_pay().
        $key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
        // phpcs:enable

        if ( ! $order instanceof \WC_Order || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
            return null;
        }

        return ( new PreviewOrderRepository() )->is_sample( $order ) ? $order : null;
    }
}
