<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the active layout the checkout page: our template in place of the
 * theme's, the theme's header and footer around the layout's blocks (the
 * root block is the form). WooCommerce's own validation is untouched.
 *
 * Stands down entirely, leaving WooCommerce untouched, when safe mode is on
 * or no layout resolves; a preview link (PreviewRequest) renders its layout
 * regardless. Full rules in docs/system-architecture.md, § Storefront.
 */
class CheckoutPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

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
            return ( new LayoutRepository() )->resolve_for_request( $preview_id );
        }

        if ( ! empty( ( new SettingsRepository() )->get()->general['safe_mode'] ) ) {
            return null;
        }

        return ( new LayoutRepository() )->resolve_for_request();
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
