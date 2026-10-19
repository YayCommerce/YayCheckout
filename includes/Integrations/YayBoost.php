<?php
namespace YayCheckout\Integrations;

use YayCheckout\Helpers\PluginBlockCatalogue;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * YayBoost, read through its public `yayboost_register_features` action only: which edition runs, whether Pro is
 * licensed, and whether a feature is on. PluginBlockCatalogue resolves its card states from it.
 *
 * YayBoost's checkout blocks are Pro's, so until YayBoost ships shortcodes of its own, this registers them for a
 * placeholder to print, and removes YayBoost's own hook output for a feature the served layout has a spot for, so
 * it prints once. Every call into YayBoost is guarded: a YayBoost that renames a method yields '' or a null state,
 * never a fatal.
 */
class YayBoost {

    use SingletonTrait;

    /** YayBoost's feature ids, as its FeatureRegistry keys them. */
    public const ORDER_BUMP        = 'order_bump';
    public const FREE_SHIPPING_BAR = 'free_shipping_bar';

    /** The shortcodes a placeholder prints for those features. */
    public const ORDER_BUMP_SHORTCODE        = 'yayboost_order_bump';
    public const FREE_SHIPPING_BAR_SHORTCODE = 'yayboost_free_shipping_bar';

    /** Shortcode => the method that prints it, registered only while YayBoost doesn't register its own. */
    private const SHORTCODES = [
        self::ORDER_BUMP_SHORTCODE        => 'order_bump_shortcode',
        self::FREE_SHIPPING_BAR_SHORTCODE => 'free_shipping_bar_shortcode',
    ];

    /** The hooks YayBoost prints its order bumps on, one per bump position (OrderBumpRenderer). */
    private const ORDER_BUMP_HOOKS = [
        'woocommerce_review_order_after_cart_contents',
        'woocommerce_review_order_before_payment',
        'woocommerce_review_order_before_submit',
    ];

    /** Feature id => the method that removes its hook output. */
    private const UNHOOK = [
        self::ORDER_BUMP        => 'unhook_order_bumps',
        self::FREE_SHIPPING_BAR => 'unhook_shipping_bar',
    ];

    /** The checkout hooks YayBoost prints its free shipping bar on. */
    private const SHIPPING_BAR_HOOKS = [ 'woocommerce_before_checkout_form', 'woocommerce_after_checkout_form' ];

    /** YayBoost's FeatureRegistry, once YayBoost has booted its features this request; null otherwise. */
    private $registry = null;

    /** @var string[] The feature ids the served layout has a spot for, once stop_default_output() ran. */
    private array $spotted = [];

    protected function __construct() {
        if ( ! defined( 'YAYBOOST_VERSION' ) ) {
            return;
        }

        // YayBoost boots on plugins_loaded 20, after YayCheckout; unlicensed Pro never fires it.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- YayBoost's action.
        add_action( 'yayboost_register_features', [ $this, 'capture_registry' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'wp', [ $this, 'stop_default_output' ] );
        // The contract YayBoost is asked to honour before printing on its hooks; until then stop_default_output() unhooks.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- YayBoost's filter.
        add_filter( 'yayboost_render_default_output', [ $this, 'render_default_output' ], 10, 2 );
    }

    /** @param object $registry YayBoost's FeatureRegistry. */
    public function capture_registry( $registry ): void {
        $this->registry = is_object( $registry ) ? $registry : null;
    }

    /** True for YayBoost Pro: only its adapter can check a license. */
    public function is_pro(): bool {
        return class_exists( 'YayboostPluginAdapter', false ) && method_exists( 'YayboostPluginAdapter', 'is_licensed' );
    }

    /** Pro's license state; null for Free or when YayBoost isn't loaded. */
    public function is_licensed(): ?bool {
        return $this->is_pro() ? (bool) \YayboostPluginAdapter::is_licensed() : null;
    }

    /** One of YayBoost's features, e.g. order_bump; null before YayBoost booted them. */
    public function feature( string $feature_id ): ?object {
        if ( null === $this->registry || ! method_exists( $this->registry, 'get' ) ) {
            return null;
        }

        $feature = $this->registry->get( $feature_id );

        return is_object( $feature ) ? $feature : null;
    }

    /** Whether a feature is on; null when YayBoost can't tell. */
    public function feature_enabled( string $feature_id ): ?bool {
        $feature = $this->feature( $feature_id );

        return null !== $feature && method_exists( $feature, 'is_enabled' ) ? (bool) $feature->is_enabled() : null;
    }

    public function register_shortcodes(): void {
        if ( null === $this->registry ) {
            return;
        }

        foreach ( self::SHORTCODES as $tag => $method ) {
            if ( ! shortcode_exists( $tag ) ) {
                add_shortcode( $tag, [ $this, $method ] );
            }
        }
    }

    /** Every active bump, as YayBoost Pro's Order Bump block prints them. */
    public function order_bump_shortcode(): string {
        return $this->render(
            self::ORDER_BUMP,
            static function ( $feature ): string {
                $renderer = method_exists( $feature, 'get_renderer' ) ? $feature->get_renderer() : null;

                return is_object( $renderer ) && method_exists( $renderer, 'render_all_bumps_for_block' ) ? (string) $renderer->render_all_bumps_for_block() : '';
            }
        );
    }

    public function free_shipping_bar_shortcode(): string {
        return $this->render(
            self::FREE_SHIPPING_BAR,
            static function ( $feature ): string {
                if ( ! method_exists( $feature, 'render_bar' ) ) {
                    return '';
                }

                ob_start();
                $feature->render_bar();

                return (string) ob_get_clean();
            }
        );
    }

    /**
     * What $output returns for an enabled feature, or ''. YayBoost's internals are no contract (an off feature's
     * get_renderer() throws a TypeError), so anything thrown leaves the spot empty instead of the checkout broken.
     */
    private function render( string $feature_id, callable $output ): string {
        if ( true !== $this->feature_enabled( $feature_id ) ) {
            return '';
        }

        $level = ob_get_level();

        try {
            return $output( $this->feature( $feature_id ) );
        } catch ( \Throwable $error ) {
            while ( ob_get_level() > $level ) {
                ob_end_clean();
            }

            return '';
        }
    }

    /**
     * Removes YayBoost's own hook output for each feature the served layout has a spot for: on the checkout page a
     * layout takes over, and on the update_order_review refresh, which reprints the payment area.
     */
    public function stop_default_output(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Request-type detection only.
        $refresh = isset( $_GET['wc-ajax'] ) && 'update_order_review' === sanitize_key( wp_unslash( $_GET['wc-ajax'] ) );
        $page    = CheckoutPage::get_instance();

        if ( null === $this->registry || ! ( $refresh || $page->takes_over() ) || null === $page->layout() ) {
            return;
        }

        $spots = PluginBlockCatalogue::spots_in( $page->layout()->content );

        foreach ( PluginBlockCatalogue::entries() as $entry ) {
            if ( 'yayboost' === $entry['plugin'] && isset( self::UNHOOK[ $entry['featureId'] ] ) && in_array( $entry['target'], $spots, true ) ) {
                $this->spotted[] = $entry['featureId'];
                $this->{ self::UNHOOK[ $entry['featureId'] ] }();
            }
        }
    }

    public function render_default_output( bool $render, string $feature_id ): bool {
        return in_array( $feature_id, $this->spotted, true ) ? false : $render;
    }

    /**
     * YayBoost hooks its bumps as closures bound to OrderBumpFeature (Free 1.3.0) or as its renderer's render()
     * (Pro 1.3.2), so a callback is YayBoost's when its object is either one.
     */
    private function unhook_order_bumps(): void {
        global $wp_filter;

        $feature = $this->feature( self::ORDER_BUMP );

        if ( null === $feature ) {
            return;
        }

        $owners = [ $feature ];

        try {
            $owners[] = method_exists( $feature, 'get_renderer' ) ? $feature->get_renderer() : null;
        } catch ( \Throwable $error ) {
            // Off features have no renderer; the closures still match the feature.
            unset( $error );
        }

        foreach ( self::ORDER_BUMP_HOOKS as $hook ) {
            if ( ! isset( $wp_filter[ $hook ] ) ) {
                continue;
            }

            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    if ( in_array( self::callback_object( $callback['function'] ), $owners, true ) ) {
                        remove_action( $hook, $callback['function'], $priority );
                    }
                }
            }
        }
    }

    /** The object a callback runs on: a closure's bound $this, or the object of an [object, method] pair. */
    private static function callback_object( $callback ): ?object {
        if ( $callback instanceof \Closure ) {
            return ( new \ReflectionFunction( $callback ) )->getClosureThis();
        }

        return is_array( $callback ) && isset( $callback[0] ) && is_object( $callback[0] ) ? $callback[0] : null;
    }

    private function unhook_shipping_bar(): void {
        $feature = $this->feature( self::FREE_SHIPPING_BAR );

        if ( null === $feature ) {
            return;
        }

        foreach ( self::SHIPPING_BAR_HOOKS as $hook ) {
            $priority = has_action( $hook, [ $feature, 'render_bar' ] );

            if ( false !== $priority ) {
                remove_action( $hook, [ $feature, 'render_bar' ], $priority );
            }
        }
    }
}
