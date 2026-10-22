<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * On the requests a layout renders, and in the layout editor so its canvas
 * matches, WordPress loads the bundled themeless theme (templates/themeless/)
 * in place of the site's: the theme's functions.php, styles, scripts and
 * theme.json never load.
 *
 * Decided at setup_theme, before core includes the theme, so the request is
 * matched from its URL: the main query does not exist yet. The swap mirrors
 * core's customizer theme preview, so wp_get_theme(), the directories and
 * theme.json all name the same theme.
 */
class ThemelessCheckout {
    use SingletonTrait;

    public const THEME = 'yaycheckout-themeless';

    /** The checkout form's wc-ajax calls, besides ours (yaycheckout_*). Not get_refreshed_fragments: every page sends it. */
    private const WC_AJAX_ACTIONS = [ 'update_order_review', 'checkout', 'apply_coupon', 'remove_coupon' ];

    private bool $active = false;

    protected function __construct() {
        // After PreviewRequest::read_token() at 0.
        add_action( 'setup_theme', [ $this, 'maybe_swap' ], 1 );
    }

    /** True when this request loads the themeless theme. */
    public function is_active(): bool {
        return $this->active;
    }

    public function maybe_swap(): void {
        $storefront = $this->is_storefront_request() && (
            ( $this->is_checkout_form_request() && $this->resolves_layout() )
            || ( $this->is_order_received_request() && $this->resolves_thank_you_layout() )
        );

        if ( ! $storefront && ! $this->is_layout_editor_request() ) {
            return;
        }

        /**
         * Whether the checkout a layout renders, and the layout editor, load the themeless theme instead of the site's.
         * Runs at setup_theme, so only a plugin or mu-plugin can hook it: the theme's functions.php is what it skips.
         *
         * @param bool $themeless Default true.
         */
        if ( ! apply_filters( 'yaycheckout_themeless_checkout', true ) ) {
            return;
        }

        register_theme_directory( self::theme_root() );

        foreach ( [ 'template', 'stylesheet', 'pre_option_template', 'pre_option_stylesheet' ] as $hook ) {
            add_filter( $hook, [ self::class, 'theme_slug' ] );
        }
        add_filter( 'pre_option_current_theme', [ self::class, 'theme_name' ] );
        // Without these, get_raw_theme_root() looks the slug up in the cached theme_roots transient, which lacks it.
        add_filter( 'pre_option_template_root', [ self::class, 'theme_root' ] );
        add_filter( 'pre_option_stylesheet_root', [ self::class, 'theme_root' ] );

        $this->active = true;
    }

    public static function theme_slug(): string {
        return self::THEME;
    }

    public static function theme_name(): string {
        return 'YayCheckout Themeless';
    }

    /** Absolute, as registered: get_theme_root() uses a registered root as is. */
    public static function theme_root(): string {
        return YAYCHECKOUT_PLUGIN_DIR . 'templates/themeless';
    }

    /** A front-end page or wc-ajax request; not admin, admin-ajax, REST, cron, the customizer, XML-RPC or WP-CLI. */
    private function is_storefront_request(): bool {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
            return false;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing, as core's own parse_request.
        if ( isset( $_GET['rest_route'] ) || isset( $_GET['customize_changeset_uuid'] ) || isset( $_GET['customize_theme'] ) ) {
            return false;
        }
        // phpcs:enable

        return 0 !== strpos( $this->request_path(), rest_get_url_prefix() );
    }

    /**
     * The checkout form or one of its wc-ajax calls. Not order-received or order-pay: they share the
     * checkout page, render WooCommerce's templates, and keep the theme.
     */
    private function is_checkout_form_request(): bool {
        $checkout_id = wc_get_page_id( 'checkout' );

        if ( $checkout_id <= 0 ) {
            return false;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing, as core's own parse_request.
        if ( isset( $_GET['wc-ajax'] ) ) {
            $action = sanitize_key( wp_unslash( $_GET['wc-ajax'] ) );

            return in_array( $action, self::WC_AJAX_ACTIONS, true ) || 0 === strpos( $action, 'yaycheckout_' );
        }

        if ( isset( $_GET['order-received'] ) || isset( $_GET['order-pay'] ) ) {
            return false;
        }

        // Plain permalinks.
        if ( isset( $_GET['page_id'] ) ) {
            return absint( $_GET['page_id'] ) === $checkout_id;
        }

        if ( isset( $_GET['pagename'] ) ) {
            return trim( sanitize_text_field( wp_unslash( $_GET['pagename'] ) ), '/' ) === get_page_uri( $checkout_id );
        }
        // phpcs:enable

        // Pretty permalinks: the page's path exactly; a longer one is an endpoint such as checkout/order-received/123.
        $path = preg_replace( '#^index\.php(/|$)#', '', $this->request_path() );

        return '' !== $path && get_page_uri( $checkout_id ) === $path;
    }

    /**
     * The order-received endpoint on the checkout page. Not order-pay: that stays WooCommerce's
     * pay-for-order screen. Matched from the URL at setup_theme, before the main query exists.
     */
    private function is_order_received_request(): bool {
        $checkout_id = wc_get_page_id( 'checkout' );

        if ( $checkout_id <= 0 ) {
            return false;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing, as core's own parse_request.
        if ( isset( $_GET['wc-ajax'] ) || isset( $_GET['order-pay'] ) ) {
            return false;
        }

        if ( isset( $_GET['order-received'] ) ) {
            if ( isset( $_GET['page_id'] ) ) {
                return absint( $_GET['page_id'] ) === $checkout_id;
            }

            return true;
        }
        // phpcs:enable

        $path         = preg_replace( '#^index\.php(/|$)#', '', $this->request_path() );
        $checkout_uri = get_page_uri( $checkout_id );

        return '' !== $path && '' !== $checkout_uri && 0 === strpos( $path, $checkout_uri . '/order-received' );
    }

    /**
     * The layout editor, so its canvas gets the theme.json and editor styles the storefront gets: the
     * editor screen of a layout, and the REST calls its embed tags (LayoutEditorEmbed::REST_HEADER).
     */
    private function is_layout_editor_request(): bool {
        global $pagenow;

        if ( isset( $_SERVER['HTTP_X_YAYCHECKOUT_EMBED'] ) ) {
            return 0 === strpos( $this->request_path(), rest_get_url_prefix() ) || isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( ! is_admin() || wp_doing_ajax() ) {
            return false;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
        if ( 'post.php' === $pagenow && isset( $_GET['post'], $_GET['action'] ) && 'edit' === $_GET['action'] ) {
            return Layout::POST_TYPE === get_post_type( absint( $_GET['post'] ) );
        }

        return 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && Layout::POST_TYPE === $_GET['post_type'];
        // phpcs:enable
    }

    /** What CheckoutPage::resolve() decides, without the view swap, which never changes whether a layout resolves. */
    private function resolves_layout(): bool {
        $preview_id = PreviewRequest::get_instance()->layout_id();

        if ( null !== $preview_id ) {
            return null !== ( new LayoutRepository() )->find( $preview_id );
        }

        if ( ( new SettingsRepository() )->is_safe_mode() ) {
            return false;
        }

        return null !== ( new LayoutRepository() )->active( CheckoutPage::shopper_context() );
    }

    /** What ThankYouPage needs before takeover, short of the order: safe mode off and an active layout with a thank-you page. */
    private function resolves_thank_you_layout(): bool {
        if ( ( new SettingsRepository() )->is_safe_mode() ) {
            return false;
        }

        $layout = ( new LayoutRepository() )->active( CheckoutPage::shopper_context() );

        return null !== $layout && $layout->has_thank_you();
    }

    /** The request path below home, decoded, without slashes at either end. */
    private function request_path(): string {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared, never printed.
        $path = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
        $home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

        if ( '' !== trim( $home, '/' ) && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
            $path = substr( $path, strlen( rtrim( $home, '/' ) ) );
        }

        return trim( $path, '/' );
    }
}
