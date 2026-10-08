<?php
namespace YayCheckout\Renderer;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\PreviewToken;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a checkout-page request carrying `?yaycheckout_preview=TOKEN` into a
 * preview of that layout: sample cart, ordering disabled, no caching.
 *
 * Off the checkout page the argument still counts: any request carrying it
 * refuses orders, and CheckoutPage::resolve() serves a valid token's layout,
 * to the field and fragment filters too.
 */
class PreviewRequest {
    use SingletonTrait;

    /** Cart-item data key marking sample products, so they can be stripped from every non-preview request. */
    public const CART_ITEM_FLAG = 'yaycheckout_preview';

    private const SAMPLE_PRODUCTS = 2;

    private string $token         = '';
    private bool $token_presented = false;
    private ?int $layout_id       = null;
    private ?int $expires_at      = null;
    private bool $no_products     = false;

    protected function __construct() {
        add_action( 'init', [ $this, 'read_token' ], 5 );
        // Before CheckoutPage::maybe_take_over() at 10: the 410 guard must win over the render.
        add_action( 'wp', [ $this, 'guard_and_attach' ], 5 );
        add_action( 'wp', [ $this, 'prefill_cart' ], 6 );
        add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'strip_sample_items' ] );
    }

    public function layout_id(): ?int {
        return $this->layout_id;
    }

    public function token(): string {
        return $this->token;
    }

    public function expires_at(): ?int {
        return $this->expires_at;
    }

    /** True once the request carries a valid token and is the checkout page or a wc-ajax call. */
    public function active(): bool {
        return null !== $this->layout_id && ( $this->is_wc_ajax() || $this->on_checkout_page() );
    }

    /** No products were purchasable when the sample cart was built; the banner says so. */
    public function no_products(): bool {
        return $this->no_products;
    }

    public function read_token(): void {
        if ( ! isset( $_GET[ PreviewToken::QUERY_ARG ] ) || ! is_string( $_GET[ PreviewToken::QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is its own MAC.
            return;
        }

        $this->token_presented = true;
        $this->token           = sanitize_text_field( wp_unslash( $_GET[ PreviewToken::QUERY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Any request carrying the arg, valid or not: a token that expires while the page is open
        // must not turn the sample cart into a real order. Here, not at `wp`, because the non-JS
        // form POST is processed at wp_loaded.
        add_action( 'woocommerce_checkout_process', [ $this, 'refuse_order' ] );

        $parsed = PreviewToken::parse( $this->token );

        if ( null === $parsed ) {
            return;
        }

        $this->layout_id  = $parsed['id'];
        $this->expires_at = $parsed['expires_at'];
    }

    /**
     * A presented token that resolves to no layout (bad MAC, expired, deleted)
     * is a 410 on the checkout page — never a fall-through to the active layout.
     */
    public function guard_and_attach(): void {
        if ( ! $this->token_presented || ! ( $this->is_wc_ajax() || $this->on_checkout_page() ) ) {
            return;
        }

        if ( $this->on_checkout_page() && ( null === $this->layout_id || null === ( new LayoutRepository() )->find( $this->layout_id ) ) ) {
            nocache_headers();
            wp_die( esc_html__( 'This preview link has expired.', 'yaycheckout' ), '', [ 'response' => 410 ] );
        }

        // Keyed on the arg being present, not on it parsing: a wc-ajax refresh after expiry must still disable ordering.
        nocache_headers();
        add_filter( 'woocommerce_ajax_get_endpoint', [ $this, 'add_token_to_url' ] );
        add_filter( 'woocommerce_get_checkout_url', [ $this, 'add_token_to_url' ] );
        add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
        // update_order_review swaps the form for "session expired" on an empty cart; a store with no products must still show the layout.
        add_filter( 'woocommerce_checkout_update_order_review_expired', '__return_false' );
        add_filter( 'woocommerce_order_button_html', [ $this, 'disabled_order_button' ] );
    }

    /** Every wc-ajax call and the non-JS form POST carry the token; a hidden input never reaches update_order_review. */
    public function add_token_to_url( string $url ): string {
        return PreviewToken::with_token( $url, $this->token );
    }

    /** Disables the button WooCommerce or the Place order block printed, keeping its label and styles. */
    public function disabled_order_button( string $html ): string {
        $button = new \WP_HTML_Tag_Processor( $html );

        if ( ! $button->next_tag( 'BUTTON' ) ) {
            return $html;
        }

        $button->set_attribute( 'disabled', true );

        return $button->get_updated_html();
    }

    public function refuse_order(): void {
        wc_add_notice( __( 'Orders cannot be placed from a preview link.', 'yaycheckout' ), 'error' );
    }

    /** Fills an empty cart with the two newest purchasable simple products so the order table has rows. A shopper's own cart is left alone. */
    public function prefill_cart(): void {
        if ( ! $this->active() || $this->is_wc_ajax() || is_wc_endpoint_url() || ! WC()->cart->is_empty() ) {
            return;
        }

        // add_to_cart() adds an error notice on any refusal (validation filters, sold-individually); none of those belong on a preview.
        $notices = wc_get_notices();

        $products = wc_get_products(
            [
                'status'       => 'publish',
                'stock_status' => 'instock',
                'type'         => 'simple',
                'limit'        => self::SAMPLE_PRODUCTS,
                'orderby'      => 'date',
                'order'        => 'DESC',
            ]
        );

        foreach ( $products as $product ) {
            if ( $product->is_purchasable() ) {
                WC()->cart->add_to_cart( $product->get_id(), 1, 0, [], [ self::CART_ITEM_FLAG => true ] );
            }
        }

        wc_set_notices( $notices );

        $this->no_products = WC()->cart->is_empty();

        if ( ! $this->no_products ) {
            WC()->cart->calculate_totals();
        }
    }

    /** Sample items must not outlive the preview: any request without the token drops them. */
    public function strip_sample_items( \WC_Cart $cart ): void {
        if ( $this->token_presented ) {
            return;
        }

        $removed = false;

        foreach ( $cart->get_cart() as $key => $item ) {
            if ( ! empty( $item[ self::CART_ITEM_FLAG ] ) ) {
                $cart->remove_cart_item( $key );
                $removed = true;
            }
        }

        // remove_cart_item() does not persist; calculate_totals() is what writes the session.
        if ( $removed ) {
            $cart->calculate_totals();
        }
    }

    /** The banner above the layout: what is being previewed and that it is not a live checkout. */
    public function banner( Layout $layout ): void {
        echo '<div class="woocommerce-info yaycheckout-preview-banner">';
        printf(
            /* translators: 1: layout title, 2: layout status, 3: time until the link expires, e.g. "2 hours" */
            esc_html__( 'Preview of "%1$s" (%2$s) — expires in %3$s. Orders cannot be placed here.', 'yaycheckout' ),
            esc_html( $layout->title ),
            esc_html( LayoutStatus::label( $layout->post_status ) ),
            esc_html( human_time_diff( time(), (int) $this->expires_at ) )
        );

        if ( $this->no_products ) {
            echo ' ' . esc_html__( 'No products to prefill the cart.', 'yaycheckout' );
        }

        echo '</div>';
    }

    private function is_wc_ajax(): bool {
        return ! empty( $_GET['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Request-type detection only.
    }

    /** Not is_checkout(): WooCommerce memoises it per process. */
    private function on_checkout_page(): bool {
        return did_action( 'wp' ) && is_page( wc_get_page_id( 'checkout' ) );
    }
}
