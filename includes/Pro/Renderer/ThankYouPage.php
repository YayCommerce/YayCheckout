<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Engine\Admin\LayoutEditorEmbed;
use YayCheckout\Models\Layout;
use YayCheckout\Pro\Repositories\PreviewOrderRepository;
use YayCheckout\Renderer\CheckoutBaseStyles;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Renderer\PreviewRequest;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: makes the active layout's thank-you page (Layout::META_THANK_YOU) the order-received page: our
 * template in place of WooCommerce's thankyou.php, printing its blocks then the thank-you hooks
 * gateways rely on. Leaves WooCommerce untouched when safe mode is on, no layout is active, the
 * active layout has no thank-you page, or the order key is missing or wrong. A preview link renders
 * its own layout over the sample order (PreviewOrderRepository), and nothing else.
 *
 * Attaches to core through its filters only: the Thank you editor view, the themeless swap, the admin
 * bar's Edit Layout, and the live values. Free has none of it, so order-received stays WooCommerce's.
 */
class ThankYouPage {
    use SingletonTrait;

    /** After WC_Frontend_Scripts registers its handles at priority 10. */
    private const SCRIPTS_PRIORITY = 20;

    /** @var Layout|null|false false until first resolved. */
    private $layout = false;

    /** @var \WC_Order|null|false false until first resolved. */
    private $order = false;

    /** @var array<string, array{text: string, empty: bool}>|null The order's live values, read once per request. */
    private $live_values = null;

    protected function __construct() {
        add_action( 'wp', [ $this, 'maybe_take_over' ] );
        add_filter( 'yaycheckout_editor_views', [ $this, 'editor_views' ] );
        add_filter( 'yaycheckout_layout_renders_order_received', [ $this, 'renders_order_received' ] );
        add_filter( 'yaycheckout_admin_bar_layout', [ $this, 'admin_bar_layout' ] );
        add_filter( 'yaycheckout_live_values_source', [ $this, 'live_values_source' ] );
        add_filter( 'yaycheckout_live_value_labels', [ OrderLiveValues::class, 'labels' ] );
    }

    /**
     * Adds the Thank you editor view, kept in Layout::META_THANK_YOU.
     *
     * @param array<string, string> $views View => meta key.
     * @return array<string, string>
     */
    public function editor_views( $views ): array {
        $views = is_array( $views ) ? $views : [];

        $views[ LayoutEditorEmbed::VIEW_THANK_YOU ] = Layout::META_THANK_YOU;

        return $views;
    }

    /**
     * At setup_theme, on an order-received URL: whether a layout with a thank-you page would render it,
     * short of the order, which the main query has yet to name.
     *
     * @param bool $renders
     */
    public function renders_order_received( $renders ): bool {
        if ( $renders ) {
            return true;
        }

        $layout = self::preview_or_active_layout();

        return null !== $layout && $layout->has_thank_you();
    }

    /**
     * The Edit Layout item on a page this renders opens the layout's Thank you view.
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
            'view'   => LayoutEditorEmbed::VIEW_THANK_YOU,
        ];
    }

    /**
     * On a page this renders, the live value spans read the order: its totals, overview and address fields.
     *
     * @param mixed $source
     * @return mixed
     */
    public function live_values_source( $source ) {
        if ( null !== $source || ! $this->takes_over() ) {
            return $source;
        }

        $order = $this->order();

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

        // The sample order is a checkout-draft, which WooCommerce counts as payable, so Order Details would offer a Pay action.
        if ( null !== PreviewRequest::get_instance()->layout_id() ) {
            add_filter( 'woocommerce_my_account_my_orders_actions', '__return_empty_array', PHP_INT_MAX );
        }
    }

    public function page_template(): string {
        return YAYCHECKOUT_PLUGIN_DIR . 'includes/Pro/templates/thankyou-page.php';
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
     * Resolves layout and order together. Both null when this is not a valid takeover.
     */
    private function resolve(): void {
        $this->layout = null;
        $this->order  = null;

        if ( ! is_order_received_page() ) {
            return;
        }

        $layout = self::preview_or_active_layout();

        if ( null === $layout || ! $layout->has_thank_you() ) {
            return;
        }

        $order = $this->validated_order();

        // A preview link shows only the sample order, so a token can't put a layout over a shopper's order.
        if ( null === $order || ( null !== PreviewRequest::get_instance()->layout_id() && ! self::is_preview_order( $order ) ) ) {
            return;
        }

        // This request's own copy: every reader of $layout->content (the template, LiveValues, enqueue) gets the thank-you page.
        $layout->content = $layout->thank_you_content;

        $this->layout = $layout;
        $this->order  = $order;
    }

    /**
     * A preview link's layout, any state, before the safe-mode check as on the checkout: safe mode protects
     * shoppers, a preview is the merchant looking. Otherwise the active layout, unless safe mode is on.
     * renders_order_received() decides ThemelessCheckout's swap with it too.
     */
    public static function preview_or_active_layout(): ?Layout {
        $preview_id = PreviewRequest::get_instance()->layout_id();

        if ( null !== $preview_id ) {
            return ( new LayoutRepository() )->find( $preview_id );
        }

        if ( ( new SettingsRepository() )->is_safe_mode() ) {
            return null;
        }

        return ( new LayoutRepository() )->active( CheckoutPage::shopper_context() );
    }

    private static function is_preview_order( \WC_Order $order ): bool {
        $sample = ( new PreviewOrderRepository() )->find();

        return null !== $sample && $sample->get_id() === $order->get_id();
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
