<?php
namespace YayCheckout\Integrations;

use YayCheckout\Engine\Register\ScriptName;
use YayCheckout\Helpers\PluginBlockCatalogue;
use YayCheckout\Renderer\CartItemQuantity;
use YayCheckout\Renderer\CheckoutPage;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * YayBoost, read through its public `yayboost_register_features` action only: which edition runs, whether Pro is
 * licensed, and whether a feature is on. PluginBlockCatalogue resolves its card states from it.
 *
 * A layout's spot prints a feature through a shortcode in both YayBoost editions, so until YayBoost ships
 * shortcodes of its own, this registers them for the placeholder to print, and removes YayBoost's own hook output
 * for a feature the served layout has a spot for, so it prints once. Every call into YayBoost is guarded: a YayBoost that renames a method yields '' or a null state,
 * never a fatal.
 */
class YayBoost {

    use SingletonTrait;

    /** YayBoost's feature ids, as its FeatureRegistry keys them. */
    public const ORDER_BUMP        = 'order_bump';
    public const FREE_SHIPPING_BAR = 'free_shipping_bar';
    public const FBT               = 'frequently_bought_together';

    /** The catalogue target a layout's order bump spot holds, named after YayBoost Pro's Order Bump block. */
    public const ORDER_BUMP_BLOCK = 'yayboost/order-bump';

    /** The catalogue target a layout's Frequently bought together spot holds; YayBoost has no such block yet. */
    public const FBT_BLOCK = 'yayboost/frequently-bought-together';

    /**
     * Feature id => [ its catalogue target, the class on its spot's outer element ], for the spots update_order_review
     * reprints (spot_fragments()).
     */
    private const REPRINTED_SPOTS = [
        self::ORDER_BUMP => [ self::ORDER_BUMP_BLOCK, 'yaycheckout-order-bump-spot' ],
        self::FBT        => [ self::FBT_BLOCK, 'yaycheckout-fbt-spot' ],
    ];

    /** The shortcodes a placeholder prints for those features. */
    public const ORDER_BUMP_SHORTCODE        = 'yayboost_order_bump';
    public const FREE_SHIPPING_BAR_SHORTCODE = 'yayboost_free_shipping_bar';
    public const FBT_SHORTCODE               = 'yayboost_frequently_bought_together';

    /** Shortcode => the method that prints it, registered only while YayBoost doesn't register its own. */
    private const SHORTCODES = [
        self::ORDER_BUMP_SHORTCODE        => 'order_bump_shortcode',
        self::FREE_SHIPPING_BAR_SHORTCODE => 'free_shipping_bar_shortcode',
        self::FBT_SHORTCODE               => 'fbt_shortcode',
    ];

    /** Adds and removes the Frequently bought together spot's products (templates/yayboost-fbt.php). */
    private const FBT_SCRIPT = 'yaycheckout-yayboost-fbt';

    /** YayBoost's add-to-cart action for FBT products, which keeps their attribution (FBTAjaxHandler). */
    private const FBT_ADD_ACTION = 'yayboost_fbt_add_to_cart';

    /** The cart item data FBT_ADD_ACTION gives a product added from an FBT list. */
    private const FBT_CART_ITEM_KEY = '_yayboost_fbt';

    /** Pro's only order bump hook, and Free's for its default position, before_place_order. */
    private const ORDER_BUMP_DEFAULT_HOOK = 'woocommerce_review_order_before_submit';

    /** The hooks YayBoost prints its order bumps on, one per bump position (OrderBumpRenderer). */
    private const ORDER_BUMP_HOOKS = [
        'woocommerce_review_order_after_cart_contents',
        'woocommerce_review_order_before_payment',
        self::ORDER_BUMP_DEFAULT_HOOK,
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
        add_filter( 'render_block', [ $this, 'mark_spot' ], 10, 2 );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'spot_fragments' ] );

        // After checkout-base: CheckoutPage enqueues it at 20 on the storefront, LayoutEditorCanvas at 10 in the editor canvas.
        add_action( 'wp_enqueue_scripts', [ $this, 'add_styles' ], 21 );
        add_action( 'enqueue_block_assets', [ $this, 'add_styles' ], 11 );
    }

    /** The order bump in the templates' card look, wherever a layout renders: the storefront checkout and the layout editor canvas. */
    public function add_styles(): void {
        if ( wp_style_is( ScriptName::STYLE_CHECKOUT_BASE ) ) {
            wp_enqueue_style( ScriptName::STYLE_YAYBOOST, YAYCHECKOUT_PLUGIN_URL . 'assets/css/yayboost.css', [ ScriptName::STYLE_CHECKOUT_BASE ], YAYCHECKOUT_VERSION );
        }
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

    /**
     * The hooks YayBoost prints its order bumps on in a layout without a spot, in ORDER_BUMP_HOOKS order: Pro's one,
     * or the hook of each active bump's position in Free. A bump's conditions are left out: the editor shows where a
     * bump can land, not whether a cart triggers it. Empty while the feature is off, no bump is active, or YayBoost's
     * BumpRepository can't be read.
     *
     * @return string[]
     */
    public function order_bump_hooks(): array {
        $repository = '\YayBoost\Features\OrderBump\BumpRepository';
        $renderer   = '\YayBoost\Features\OrderBump\OrderBumpRenderer';

        if ( true !== $this->feature_enabled( self::ORDER_BUMP ) || ! method_exists( $repository, 'get_active' ) ) {
            return [];
        }

        try {
            $bumps = ( new $repository() )->get_active();
        } catch ( \Throwable $error ) {
            return [];
        }

        if ( ! is_array( $bumps ) || [] === $bumps ) {
            return [];
        }

        // Pro hooks only before_submit and ignores each bump's position.
        if ( $this->is_pro() ) {
            return [ self::ORDER_BUMP_DEFAULT_HOOK ];
        }

        // Free maps each bump's position to its hook; a build without the mapper prints every bump before submit.
        if ( ! method_exists( $renderer, 'get_hook_for_position' ) ) {
            return [ self::ORDER_BUMP_DEFAULT_HOOK ];
        }

        $hooks = [];

        foreach ( $bumps as $bump ) {
            $hooks[] = (string) $renderer::get_hook_for_position( (string) ( $bump['settings']['position'] ?? 'before_place_order' ) );
        }

        return array_values( array_intersect( self::ORDER_BUMP_HOOKS, $hooks ) );
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

    /**
     * Every active bump, in one wrapper. Free returns them from
     * render_all_bumps_for_block(); Pro lacks it and prints them from render(), the method it hooks.
     */
    public function order_bump_shortcode(): string {
        return $this->render(
            self::ORDER_BUMP,
            static function ( $feature ): string {
                $renderer = method_exists( $feature, 'get_renderer' ) ? $feature->get_renderer() : null;

                if ( ! is_object( $renderer ) ) {
                    return '';
                }

                if ( method_exists( $renderer, 'render_all_bumps_for_block' ) ) {
                    return (string) $renderer->render_all_bumps_for_block();
                }

                if ( ! method_exists( $renderer, 'render' ) ) {
                    return '';
                }

                ob_start();
                $renderer->render();

                return (string) ob_get_clean();
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
     * The products YayBoost's Frequently bought together pairs with the cart, drawn like the templates' cross-sell
     * list. FBT recommends for one product and prints only on the product page, so this asks it for each cart item
     * the shopper didn't add from an FBT list, and merges the answers, first come first kept, up to the count FBT shows for one product. Its own filter
     * decides whether a product already in the cart drops out or stays listed as added (hide_if_in_cart).
     */
    public function fbt_shortcode(): string {
        return $this->render(
            self::FBT,
            function ( $feature ): string {
                $renderer = '\YayBoost\Features\FrequentlyBoughtTogether\FBTRenderer';
                $currency = '\YayBoost\Currency\NullCurrencyProvider';

                if ( ! class_exists( $renderer ) || ! class_exists( $currency ) || ! method_exists( $feature, 'get_cache_manager' ) || ! method_exists( $feature, 'get_settings' ) || ! WC()->cart instanceof \WC_Cart ) {
                    return '';
                }

                $settings = (array) $feature->get_settings();
                // get_prepared_products() reads no prices, so it needs no real currency provider.
                $fbt = new $renderer( $feature->get_cache_manager(), $settings, new $currency() );

                // Product id => its cart item key; a variation's cart item carries its parent's id, as FBT's products do.
                $in_cart = [];
                // The products FBT recommends for: not those added from an FBT list, which YayBoost marks, so adding
                // one doesn't pull its own pairings into the list it came from.
                $sources = [];

                foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
                    $in_cart += [ (int) $cart_item['product_id'] => (string) $key ];

                    if ( ! isset( $cart_item[ self::FBT_CART_ITEM_KEY ] ) ) {
                        $sources[] = (int) $cart_item['product_id'];
                    }
                }

                $items = [];

                foreach ( array_unique( $sources ) as $source_id ) {
                    $prepared = $fbt->get_prepared_products( $source_id );

                    foreach ( (array) ( $prepared['products'] ?? [] ) as $product ) {
                        if ( $product instanceof \WC_Product && ! isset( $items[ $product->get_id() ] ) ) {
                            $items[ $product->get_id() ] = [
                                'product'       => $product,
                                'source_id'     => (int) $prepared['product_id'],
                                'cart_item_key' => $in_cart[ $product->get_id() ] ?? '',
                            ];
                        }
                    }
                }

                // FBTRenderer::get_prepared_products()'s cap: Free shows 3, Pro its setting.
                $items = array_slice( array_values( $items ), 0, $this->is_pro() ? (int) ( $settings['max_products'] ?? 4 ) : 3 );

                if ( [] === $items ) {
                    return '';
                }

                wp_enqueue_script( self::FBT_SCRIPT, YAYCHECKOUT_PLUGIN_URL . 'assets/js/checkout-yayboost-fbt.js', [ 'jquery', 'wc-checkout' ], YAYCHECKOUT_VERSION, true );

                $title = '' !== trim( (string) ( $settings['section_title'] ?? '' ) ) ? (string) $settings['section_title'] : __( 'Frequently bought together', 'yaycheckout' );

                return self::fbt_html( $items, $title );
            }
        );
    }

    /**
     * The spot's markup, with what its script needs: YayBoost's add-to-cart action and nonce, and CartItemQuantity's
     * nonce to remove an added product.
     *
     * @param array<int, array{product: \WC_Product, source_id: int, cart_item_key: string}> $items
     */
    private static function fbt_html( array $items, string $title ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The template reads them.
        $wrapper = [
            'ajax-url'   => admin_url( 'admin-ajax.php' ),
            'add-action' => self::FBT_ADD_ACTION,
            'add-nonce'  => wp_create_nonce( self::FBT_ADD_ACTION ),
            'cart-nonce' => wp_create_nonce( CartItemQuantity::NONCE_ACTION ),
            'error-text' => __( 'The cart could not be updated. Please try again.', 'yaycheckout' ),
        ];

        ob_start();
        include YAYCHECKOUT_PLUGIN_DIR . 'templates/yayboost-fbt.php';

        return (string) ob_get_clean();
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
            if ( 'yayboost' !== $entry['plugin'] || '' === $entry['featureId'] || ! in_array( $entry['target'], $spots, true ) ) {
                continue;
            }

            $this->spotted[] = $entry['featureId'];

            // Frequently bought together prints nothing on checkout.
            if ( isset( self::UNHOOK[ $entry['featureId'] ] ) ) {
                $this->{ self::UNHOOK[ $entry['featureId'] ] }();
            }
        }
    }

    public function render_default_output( bool $render, string $feature_id ): bool {
        return $this->has_spot( $feature_id ) ? false : $render;
    }

    /**
     * Tags a reprinted spot's output with its REPRINTED_SPOTS class, and prints a hidden stand-in when it printed
     * nothing, so spot_fragments() always has an element to replace.
     *
     * @param string $html  The block's output.
     * @param array  $block The parsed block.
     */
    public function mark_spot( $html, $block ): string {
        $html       = (string) $html;
        $spot_class = $this->spot_class( (array) $block );

        if ( '' === $spot_class ) {
            return $html;
        }

        $tags = new \WP_HTML_Tag_Processor( $html );

        if ( ! $tags->next_tag() ) {
            return '<div class="' . $spot_class . '" hidden></div>';
        }

        $tags->add_class( $spot_class );

        return $tags->get_updated_html();
    }

    /**
     * Reprints the order bump and Frequently bought together spots on update_order_review, since a layout's spot
     * sits outside every fragment. YayBoost ticks a bump only when it prints it, and its script reads the tick back
     * from the page, so a bump removed from the Items block stayed ticked; FBT's products follow the cart. A layout
     * holds one spot per feature: the first is reprinted.
     *
     * @param mixed $fragments Selector => HTML.
     * @return mixed
     */
    public function spot_fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || [] === $this->spotted || null === $layout ) {
            return $fragments;
        }

        foreach ( LayoutMarkupReader::flatten( parse_blocks( $layout->content ) ) as $block ) {
            $spot_class = $this->spot_class( $block );

            if ( '' !== $spot_class && ! isset( $fragments[ '.' . $spot_class ] ) ) {
                // mark_spot() tags the result.
                $fragments[ '.' . $spot_class ] = render_block( $block );
            }
        }

        return $fragments;
    }

    /** Whether the served layout has a spot for the feature, once stop_default_output() ran. */
    private function has_spot( string $feature_id ): bool {
        return in_array( $feature_id, $this->spotted, true );
    }

    /** The REPRINTED_SPOTS class for a spot of a feature the served layout has a spot for, or ''. */
    private function spot_class( array $block ): string {
        foreach ( self::REPRINTED_SPOTS as $feature_id => list( $target, $spot_class ) ) {
            if ( $this->has_spot( $feature_id ) && '' !== PluginBlockCatalogue::spot_for( $block, [ $target ] ) ) {
                return $spot_class;
            }
        }

        return '';
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
