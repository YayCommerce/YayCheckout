<?php
namespace YayCheckout\Renderer;

use YayCheckout\Enums\ConditionType;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the active layout the checkout page: our template in place of the
 * theme's, the theme's header and footer around the layout's blocks (the
 * root block is the form).
 *
 * Stands down entirely, leaving WooCommerce untouched, when safe mode is on
 * or no layout resolves; a preview link (PreviewRequest) renders its layout
 * regardless. Full rules in docs/system-architecture.md, § Storefront.
 */
class CheckoutPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

    /** WC session key: the layout id the last checkout page load served; 0 when it served WooCommerce's own. */
    private const SESSION_PINNED = 'yaycheckout_layout_id';

    /** @var Layout|null|false false until first resolved. */
    private $layout = false;

    protected function __construct() {
        add_action( 'wp', [ $this, 'maybe_take_over' ] );
    }

    /** The layout to render, or null when the renderer stands down. Memoised per request. */
    public function layout(): ?Layout {
        if ( false === $this->layout ) {
            $this->layout = $this->resolve();
        }

        return $this->layout;
    }

    public function maybe_take_over(): void {
        if ( ! $this->on_checkout_page() || null === $this->layout() ) {
            return;
        }

        // PHP_INT_MAX: must run after block themes swap in template-canvas.php.
        add_filter( 'template_include', [ $this, 'page_template' ], PHP_INT_MAX );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], self::SCRIPTS_PRIORITY );
    }

    public function page_template(): string {
        return YAYCHECKOUT_PLUGIN_DIR . 'templates/checkout-page.php';
    }

    /** Woo only enqueues these on is_checkout(); our template needs them regardless of theme. */
    public function enqueue_scripts(): void {
        wp_enqueue_script( 'wc-checkout' );
        wp_enqueue_script( 'wc-country-select' );
        wp_enqueue_script( 'wc-address-i18n' );
    }

    private function resolve(): ?Layout {
        $preview_id = PreviewRequest::get_instance()->layout_id();

        // Before the safe-mode check: safe mode protects shoppers, a preview is the merchant looking.
        if ( null !== $preview_id ) {
            return ( new LayoutRepository() )->find( $preview_id );
        }

        if ( ! empty( ( new SettingsRepository() )->get()->general['safe_mode'] ) ) {
            return null;
        }

        return $this->assigned_layout();
    }

    /**
     * The layout this request serves: picked on the checkout page load, then reused from the session pin while it stays active.
     * See docs/system-architecture.md, § Storefront "Which layout".
     */
    private function assigned_layout(): ?Layout {
        $repository   = new LayoutRepository();
        $session      = WC()->session;
        $pin          = null !== $session ? $session->get( self::SESSION_PINNED ) : null;
        $is_page_load = $this->on_checkout_page() && ! wp_doing_ajax() && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Compared to a literal only.

        if ( ! $is_page_load && null !== $pin ) {
            if ( 0 === (int) $pin ) {
                return null;
            }

            if ( $repository->is_active( (int) $pin ) ) {
                return $repository->find( (int) $pin );
            }
        }

        $layout = $repository->active( $this->shopper_context() );

        if ( $is_page_load && null !== $session ) {
            $session->set( self::SESSION_PINNED, null !== $layout ? $layout->id : 0 );
        }

        return $layout;
    }

    /**
     * What assignment conditions test, read once for this request.
     *
     * @return array{country: string, roles: string[], product_ids: int[], cart_total: float, device: string}
     */
    private function shopper_context(): array {
        $customer = WC()->customer;
        $cart     = WC()->cart;

        return [
            'country'     => null !== $customer ? $customer->get_billing_country() : '',
            'roles'       => array_values( wp_get_current_user()->roles ),
            'product_ids' => null !== $cart ? self::cart_product_ids( $cart ) : [],
            'cart_total'  => null !== $cart ? self::cart_total( $cart ) : 0.0,
            // ponytail: wp_is_mobile() counts tablets as mobile; a tablet value needs its own detection.
            'device'      => wp_is_mobile() ? ConditionType::MOBILE : ConditionType::DESKTOP,
        ];
    }

    /**
     * Product and variation ids in the cart: a rule naming a variable product matches any of its variations.
     *
     * @return int[]
     */
    private static function cart_product_ids( \WC_Cart $cart ): array {
        $ids = [];
        foreach ( $cart->get_cart() as $item ) {
            $ids[] = (int) $item['product_id'];
            $ids[] = (int) ( $item['variation_id'] ?? 0 );
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /** Items after discounts, before shipping and fees, as the store displays prices. */
    private static function cart_total( \WC_Cart $cart ): float {
        $discount = (float) $cart->get_discount_total() + ( $cart->display_prices_including_tax() ? (float) $cart->get_discount_tax() : 0.0 );

        return (float) $cart->get_displayed_subtotal() - $discount;
    }

    /**
     * True when this request is the checkout form, not a checkout-page endpoint.
     *
     * Not is_checkout(): WooCommerce memoises it per process. Order-received
     * and order-pay share the checkout page; taking them over reprints the
     * form instead of Woo's thank-you / pay templates.
     */
    private function on_checkout_page(): bool {
        if ( ! is_page( wc_get_page_id( 'checkout' ) ) ) {
            return false;
        }

        return ! is_order_received_page() && ! is_checkout_pay_page();
    }
}
