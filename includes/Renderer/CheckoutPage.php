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
 * theme's, printing the layout's blocks in its own document without the
 * theme's header or footer (the root block is the form).
 *
 * Stands down entirely, leaving WooCommerce untouched, when no layout resolves,
 * or when safe mode is on and the layout has a problem (CheckoutHealth) or last
 * failed to render; a preview link (PreviewRequest) renders its layout regardless.
 */
class CheckoutPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

    /**
     * The circuit breaker: the last render failure, array{layout_id: int, hash: string, reason: string, time: int},
     * hash being md5 of the layout's post_content. Holds while that layout is served with that content.
     */
    public const OPTION_RENDER_FAILURE = 'yaycheckout_render_failure';

    /** Errors that end the request mid-render with a message worth reporting; anything lighter lets the catch in page_template() run. */
    private const FATAL_ERRORS = [ E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ];

    /** Longest reason kept: the option is autoloaded, and an exception message can be arbitrarily large. */
    private const REASON_MAX_LENGTH = 500;

    private const FLOATING_LABELS_SCRIPT = 'yaycheckout-floating-labels';

    /** @var Layout|null|false false until first resolved. */
    private $layout = false;

    /** @var string The rendered layout, for the template. */
    private $html = '';

    /** @var bool True while the layout's blocks render, for record_fatal(). */
    private $rendering = false;

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

    /** True when this request is the checkout form and a layout renders it. Meaningful from `wp` on. */
    public function takes_over(): bool {
        return $this->on_checkout_page() && null !== $this->layout();
    }

    public function maybe_take_over(): void {
        if ( ! $this->takes_over() ) {
            return;
        }

        // PHP_INT_MAX: must run after block themes swap in template-canvas.php.
        add_filter( 'template_include', [ $this, 'page_template' ], PHP_INT_MAX );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], self::SCRIPTS_PRIORITY );
    }

    /**
     * Renders the layout before choosing the template, so a layout that fails to render gives this
     * request to WooCommerce's checkout ($template) with safe mode on, and trips the breaker for the next.
     *
     * @throws \Throwable A render failure during a preview, or with safe mode off.
     */
    public function page_template( string $template ): string {
        $layout    = $this->layout();
        $preview   = PreviewRequest::get_instance()->active();
        $safe_mode = ( new SettingsRepository() )->is_safe_mode();
        $level     = ob_get_level();

        // A preview is the merchant looking: its failures show as they are and never trip the breaker.
        if ( ! $preview ) {
            $this->rendering = true;
            register_shutdown_function( [ $this, 'record_fatal' ], $layout );
        }

        try {
            $html = $this->render( $layout );
        } catch ( \Throwable $ex ) {
            // Drop whatever the blocks printed before the throw, or half a layout leaks into WooCommerce's page.
            while ( ob_get_level() > $level ) {
                ob_end_clean();
            }

            if ( ! $this->absorb( $layout, $ex ) ) {
                throw $ex;
            }

            return $this->stand_down( $template );
        } finally {
            $this->rendering = false;
        }

        // A form without WooCommerce's submit button cannot place an order: the id is checkout.js's submit contract, printed by
        // packages/blocks/src/place-order/render.php. Safe mode off renders it anyway; the merchant is warned.
        if ( ! $preview && false === strpos( $html, 'id="place_order"' ) ) {
            $this->record_failure( $layout, __( 'The layout rendered without a Place order button.', 'yaycheckout' ) );

            if ( $safe_mode ) {
                return $this->stand_down( $template );
            }
        }

        $this->html = $html;

        return YAYCHECKOUT_PLUGIN_DIR . 'templates/checkout-page.php';
    }

    /** The rendered layout, for templates/checkout-page.php. */
    public function html(): string {
        return $this->html;
    }

    /** The breaker: the reason $layout last failed to render, while its content is unchanged since, or ''. */
    public static function render_failure( Layout $layout ): string {
        $failure = get_option( self::OPTION_RENDER_FAILURE );

        return is_array( $failure )
            && $layout->id === (int) ( $failure['layout_id'] ?? 0 )
            && md5( $layout->content ) === ( $failure['hash'] ?? '' )
            ? (string) $failure['reason']
            : '';
    }

    /**
     * Records a request that ended mid-render, by a fatal error or by a block calling exit or wp_die(),
     * so the next request falls back. Public only for register_shutdown_function().
     */
    public function record_fatal( Layout $layout ): void {
        if ( ! $this->rendering ) {
            return;
        }

        $error = error_get_last();

        $this->record_failure(
            $layout,
            null !== $error && in_array( $error['type'], self::FATAL_ERRORS, true )
                ? $error['message']
                : __( 'The layout stopped the page while rendering.', 'yaycheckout' )
        );
    }

    /**
     * Handles a layout that threw while rendering, on the page or in an update_order_review fragment:
     * records it for the next request, and says whether safe mode wants the caller to swallow it.
     * A preview records nothing and always rethrows.
     */
    public function absorb( Layout $layout, \Throwable $ex ): bool {
        if ( PreviewRequest::get_instance()->active() ) {
            return false;
        }

        $this->record_failure( $layout, '' !== $ex->getMessage() ? $ex->getMessage() : get_class( $ex ) );

        return ( new SettingsRepository() )->is_safe_mode();
    }

    /** Woo's checkout scripts, the base stylesheet, the design tokens and, for floating labels, their script. Woo only enqueues its scripts on is_checkout(); our template needs them regardless of theme. */
    public function enqueue_scripts(): void {
        CheckoutScripts::enqueue_wc_scripts();

        // The block checkout's checkout.css styles markup our layout does not print. WooCommerce's
        // general stylesheets are replaced by checkout-base.css, which their form rules would beat
        // (.woocommerce-page form .form-row input.input-text; layout's 47% floats on .form-row-first).
        wp_dequeue_style( 'wc-blocks-style-checkout' );
        foreach ( [ 'woocommerce-general', 'woocommerce-blocktheme', 'woocommerce-layout', 'woocommerce-smallscreen' ] as $handle ) {
            wp_dequeue_style( $handle );
        }

        ( new CheckoutBaseStyles() )->enqueue( true, $this->layout()->design );

        if ( $this->layout()->design->has_floating_labels() ) {
            wp_enqueue_script(
                self::FLOATING_LABELS_SCRIPT,
                YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-floating-labels.js',
                [ 'jquery' ],
                YAYCHECKOUT_VERSION,
                true
            );
        }
    }

    private function resolve(): ?Layout {
        $preview_id = PreviewRequest::get_instance()->layout_id();

        // Before the safe-mode check: safe mode protects shoppers, a preview is the merchant looking.
        if ( null !== $preview_id ) {
            return ( new LayoutRepository() )->find( $preview_id );
        }

        // Picked afresh by every request, AJAX and order submit too, with no pin: safe because every ConditionType reads a fact that holds for the life of the form.
        $layout = ( new LayoutRepository() )->active( $this->shopper_context() );

        // CheckoutHealth reads store configuration and the breaker only, so the submit request reaches the answer the render request did.
        if ( null !== $layout && ( new SettingsRepository() )->is_safe_mode() && '' !== CheckoutHealth::problem( $layout ) ) {
            return null;
        }

        return $layout;
    }

    /**
     * The layout's blocks inside the page wrapper. Rendered before wp_head(), as core's
     * template-canvas.php does: blocks enqueue script modules and styles while rendering, and on
     * block themes the import map is printed in the head. Rendered after it, a module's imports go
     * unmapped and the browser refuses to load it.
     */
    private function render( Layout $layout ): string {
        $preview = PreviewRequest::get_instance();

        ob_start();
        ?>
        <div class="woocommerce yaycheckout-page">
            <?php
            if ( $preview->active() ) {
                $preview->banner( $layout );
            }

            // Shipping packages only exist after a calculation; session totals alone leave the order table without shipping rows.
            WC()->cart->calculate_totals();

            echo do_blocks( $layout->content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup, rendered and escaped by each block.
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** Gives this request back to WooCommerce: every storefront hook reads layout() and stands down. */
    private function stand_down( string $template ): string {
        $this->layout = null;
        remove_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], self::SCRIPTS_PRIORITY );

        return $template;
    }

    /** Written only when the layout, its content or the reason changed, so a broken layout costs no write per request. */
    private function record_failure( Layout $layout, string $reason ): void {
        $reason = mb_substr( $reason, 0, self::REASON_MAX_LENGTH );

        if ( self::render_failure( $layout ) === $reason ) {
            return;
        }

        update_option(
            self::OPTION_RENDER_FAILURE,
            [
                'layout_id' => $layout->id,
                'hash'      => md5( $layout->content ),
                'reason'    => $reason,
                'time'      => time(),
            ],
            true
        );
    }

    /**
     * What assignment conditions test, read once for this request. Keyed by ConditionType.
     *
     * @return array{customer_role: string[], login_status: string, device: string}
     */
    private function shopper_context(): array {
        return [
            ConditionType::CUSTOMER_ROLE => array_values( wp_get_current_user()->roles ),
            // Not "no roles": a user of another site in the network is logged in with none here.
            ConditionType::LOGIN_STATUS  => is_user_logged_in() ? ConditionType::LOGGED_IN : ConditionType::GUEST,
            // TODO(device-detection): wp_is_mobile() counts tablets as mobile; a tablet value needs user-agent detection that core does not offer.
            ConditionType::DEVICE        => wp_is_mobile() ? ConditionType::MOBILE : ConditionType::DESKTOP,
        ];
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
