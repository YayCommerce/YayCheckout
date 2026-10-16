<?php
namespace YayCheckout\Renderer;

use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * The WooCommerce classic-checkout actions a WooCommerce Hook block can fire, and the rule that
 * moves a hook out of the block that fires it today: once the served layout places a Hook block
 * for it, only that block fires it, so plugin output never prints twice or inside another block.
 */
class WcHooks {

    public const BLOCK = 'yaycheckout/wc-hook';

    /** The root of a refreshed hook's output; with a `--<hook>` modifier it is that hook's fragment selector. */
    public const CONTENT_CLASS = 'yaycheckout-wc-hook__content';

    /** Wraps what plugins print, in a table when they print rows. */
    public const ROWS_CLASS = 'yaycheckout-wc-hook__rows';

    /**
     * Hook => where it sits in WooCommerce's templates, whether it passes WC()->checkout() as they do,
     * and whether update_order_review reprints it: plugins print totals there, which change on refresh.
     * Left out: the login hooks (their inputs belong to the login dialog's own form), the per-rate
     * woocommerce_after_shipping_rate, and woocommerce_checkout_terms_and_conditions, whose core
     * callbacks are the Terms block's content.
     */
    public const HOOKS = [
        'woocommerce_before_checkout_form'                 => [
            'group'        => 'page',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_after_checkout_form'                  => [
            'group'        => 'page',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_checkout_before_customer_details'     => [
            'group'        => 'customer',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_after_customer_details'      => [
            'group'        => 'customer',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_billing'                     => [
            'group'        => 'customer',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_shipping'                    => [
            'group'        => 'customer',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_before_checkout_billing_form'         => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_after_checkout_billing_form'          => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_before_checkout_shipping_form'        => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_after_checkout_shipping_form'         => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_before_checkout_registration_form'    => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_after_checkout_registration_form'     => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_before_order_notes'                   => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_after_order_notes'                    => [
            'group'        => 'customer',
            'checkout_arg' => true,
            'refresh'      => false,
        ],
        'woocommerce_checkout_before_order_review_heading' => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_before_order_review'         => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_order_review'                => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_after_order_review'          => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_review_order_before_cart_contents'    => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => true,
        ],
        'woocommerce_review_order_after_cart_contents'     => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => true,
        ],
        'woocommerce_review_order_before_shipping'         => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => true,
        ],
        'woocommerce_review_order_after_shipping'          => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => true,
        ],
        'woocommerce_review_order_before_order_total'      => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => true,
        ],
        'woocommerce_review_order_after_order_total'       => [
            'group'        => 'review',
            'checkout_arg' => false,
            'refresh'      => true,
        ],
        'woocommerce_review_order_before_payment'          => [
            'group'        => 'payment',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_review_order_after_payment'           => [
            'group'        => 'payment',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_review_order_before_submit'           => [
            'group'        => 'payment',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_review_order_after_submit'            => [
            'group'        => 'payment',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_before_terms_and_conditions' => [
            'group'        => 'payment',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
        'woocommerce_checkout_after_terms_and_conditions'  => [
            'group'        => 'payment',
            'checkout_arg' => false,
            'refresh'      => false,
        ],
    ];

    /** The template WooCommerce prints its review-order hooks from; an Order Summary without blocks still prints it. */
    private const REVIEW_TEMPLATE = 'checkout/review-order.php';

    /** @var array{0: string, 1: string[]}|null The layout content placed() last read, and its hooks. */
    private static ?array $placed = null;

    /** @var array<int, array<string, \WP_Hook>> Hooks detached while REVIEW_TEMPLATE prints, one entry per open print. */
    private static array $muted = [];

    /** @var array<string, callable[]> Hook => callbacks that stay where the hook has always fired, see anchor(). */
    private static array $anchored = [];

    /** @return array<string, string> Hook => its name in the editor's picker; the keys are HOOKS'. */
    public static function labels(): array {
        return [
            'woocommerce_before_checkout_form'             => __( 'Before checkout form', 'yaycheckout' ),
            'woocommerce_after_checkout_form'              => __( 'After checkout form', 'yaycheckout' ),
            'woocommerce_checkout_before_customer_details' => __( 'Before customer details', 'yaycheckout' ),
            'woocommerce_checkout_after_customer_details'  => __( 'After customer details', 'yaycheckout' ),
            'woocommerce_checkout_billing'                 => __( 'Billing', 'yaycheckout' ),
            'woocommerce_checkout_shipping'                => __( 'Shipping', 'yaycheckout' ),
            'woocommerce_before_checkout_billing_form'     => __( 'Before billing form', 'yaycheckout' ),
            'woocommerce_after_checkout_billing_form'      => __( 'After billing form', 'yaycheckout' ),
            'woocommerce_before_checkout_shipping_form'    => __( 'Before shipping form', 'yaycheckout' ),
            'woocommerce_after_checkout_shipping_form'     => __( 'After shipping form', 'yaycheckout' ),
            'woocommerce_before_checkout_registration_form' => __( 'Before account fields', 'yaycheckout' ),
            'woocommerce_after_checkout_registration_form' => __( 'After account fields', 'yaycheckout' ),
            'woocommerce_before_order_notes'               => __( 'Before order notes', 'yaycheckout' ),
            'woocommerce_after_order_notes'                => __( 'After order notes', 'yaycheckout' ),
            'woocommerce_checkout_before_order_review_heading' => __( 'Before order review heading', 'yaycheckout' ),
            'woocommerce_checkout_before_order_review'     => __( 'Before order review', 'yaycheckout' ),
            'woocommerce_checkout_order_review'            => __( 'Order review', 'yaycheckout' ),
            'woocommerce_checkout_after_order_review'      => __( 'After order review', 'yaycheckout' ),
            'woocommerce_review_order_before_cart_contents' => __( 'Before cart items', 'yaycheckout' ),
            'woocommerce_review_order_after_cart_contents' => __( 'After cart items', 'yaycheckout' ),
            'woocommerce_review_order_before_shipping'     => __( 'Before shipping totals', 'yaycheckout' ),
            'woocommerce_review_order_after_shipping'      => __( 'After shipping totals', 'yaycheckout' ),
            'woocommerce_review_order_before_order_total'  => __( 'Before order total', 'yaycheckout' ),
            'woocommerce_review_order_after_order_total'   => __( 'After order total', 'yaycheckout' ),
            'woocommerce_review_order_before_payment'      => __( 'Before payment', 'yaycheckout' ),
            'woocommerce_review_order_after_payment'       => __( 'After payment', 'yaycheckout' ),
            'woocommerce_review_order_before_submit'       => __( 'Before place order button', 'yaycheckout' ),
            'woocommerce_review_order_after_submit'        => __( 'After place order button', 'yaycheckout' ),
            'woocommerce_checkout_before_terms_and_conditions' => __( 'Before terms', 'yaycheckout' ),
            'woocommerce_checkout_after_terms_and_conditions' => __( 'After terms', 'yaycheckout' ),
        ];
    }

    public static function is_allowed( string $hook ): bool {
        return isset( self::HOOKS[ $hook ] );
    }

    public static function is_refreshed( string $hook ): bool {
        return self::is_allowed( $hook ) && self::HOOKS[ $hook ]['refresh'];
    }

    /**
     * WooCommerce's own callbacks on $hook that print what another YayCheckout block already prints:
     * the login and coupon forms, the billing and shipping forms, the review table and payment section.
     * Notices are among them because the checkout prints none of its own there today.
     *
     * @return callable[]
     */
    public static function core_callbacks( string $hook ): array {
        switch ( $hook ) {
            case 'woocommerce_before_checkout_form':
                return [ 'woocommerce_checkout_login_form', 'woocommerce_checkout_coupon_form', 'woocommerce_output_all_notices' ];
            case 'woocommerce_checkout_billing':
                return [ [ WC()->checkout(), 'checkout_form_billing' ] ];
            case 'woocommerce_checkout_shipping':
                return [ [ WC()->checkout(), 'checkout_form_shipping' ] ];
            case 'woocommerce_checkout_order_review':
                return [ 'woocommerce_order_review', 'woocommerce_checkout_payment' ];
            default:
                return [];
        }
    }

    /**
     * Keeps $callback where $hook has always fired when a Hook block takes the hook: the block that fires it
     * in place, or WooCommerce's review table, still runs it, and the Hook block doesn't. For a callback that
     * brackets that block's own output, such as one that switches something off for the Items rows and back on.
     */
    public static function anchor( string $hook, callable $callback ): void {
        self::$anchored[ $hook ][] = $callback;
    }

    /** What $hook prints without its core and anchored callbacks, or '' for a hook outside HOOKS. */
    public static function fire( string $hook ): string {
        if ( ! self::is_allowed( $hook ) ) {
            return '';
        }

        $args = self::HOOKS[ $hook ]['checkout_arg'] ? [ WC()->checkout() ] : [];

        ob_start();
        self::with_hook_trimmed(
            $hook,
            array_merge( self::core_callbacks( $hook ), self::$anchored[ $hook ] ?? [] ),
            static function () use ( $hook, $args ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- One of HOOKS, WooCommerce's own names.
                do_action( $hook, ...$args );
            }
        );

        return (string) ob_get_clean();
    }

    /** Whether anything besides core_callbacks() and anchored callbacks hooks $hook. For the editor's notice. */
    public static function has_callbacks( string $hook ): bool {
        return self::callbacks_besides( $hook, array_merge( self::core_callbacks( $hook ), self::$anchored[ $hook ] ?? [] ) ) > 0;
    }

    /**
     * How many callbacks hook $hook, at any priority, other than those of $core attached to it.
     *
     * @param callable[] $core
     */
    public static function callbacks_besides( string $hook, array $core ): int {
        global $wp_filter;

        if ( ! isset( $wp_filter[ $hook ] ) ) {
            return 0;
        }

        $count = count( array_merge( ...array_values( $wp_filter[ $hook ]->callbacks ) ) );

        foreach ( $core as $callback ) {
            if ( false !== has_action( $hook, $callback ) ) {
                --$count;
            }
        }

        return $count;
    }

    /**
     * The HOOKS the served layout's Hook blocks fire, or none when no layout is served.
     *
     * @return string[]
     */
    public static function placed(): array {
        $layout = CheckoutPage::get_instance()->layout();

        // Most layouts have no Hook block; has_block() is a string search, so they skip the parse.
        if ( null === $layout || ! has_block( self::BLOCK, $layout->content ) ) {
            return [];
        }

        if ( null === self::$placed || self::$placed[0] !== $layout->content ) {
            self::$placed = [ $layout->content, array_values( array_filter( LayoutMarkupReader::wc_hooks( $layout->content ), [ self::class, 'is_allowed' ] ) ) ];
        }

        return self::$placed[1];
    }

    /**
     * A refreshed hook's output in the root update_order_review swaps, printed even when empty so the
     * swap always has a target. The root carries BlockEmptiness's live markers.
     */
    public static function content_root( string $hook, string $output ): string {
        $is_empty = '' === trim( $output );

        return '<div class="' . esc_attr( self::CONTENT_CLASS . ' ' . self::CONTENT_CLASS . '--' . $hook ) . '" '
            . BlockEmptiness::LIVE_ATTR . '="1"' . ( $is_empty ? ' ' . BlockEmptiness::EMPTY_ATTR . '="1"' : '' ) . '>'
            . CheckoutFragments::wrap_extension_html( $output, self::ROWS_CLASS )
            . '</div>';
    }

    /**
     * Selector => fresh root for each refreshed hook a Hook block in the served layout fires, for update_order_review.
     *
     * @return array<string, string>
     */
    public static function fragments(): array {
        $fragments = [];

        foreach ( self::placed_refreshed() as $hook ) {
            $fragments[ '.' . self::CONTENT_CLASS . '--' . $hook ] = self::content_root( $hook, self::fire( $hook ) );
        }

        return $fragments;
    }

    /**
     * The placed() hooks that update_order_review reprints.
     *
     * @return string[]
     */
    public static function placed_refreshed(): array {
        return array_values( array_filter( self::placed(), [ self::class, 'is_refreshed' ] ) );
    }

    public static function is_placed( string $hook ): bool {
        return in_array( $hook, self::placed(), true );
    }

    /**
     * Fires $hook where a block has always fired it, unless a Hook block in the served layout fires it instead.
     *
     * @param mixed ...$args The hook's arguments.
     */
    public static function in_place( string $hook, ...$args ): void {
        if ( self::is_placed( $hook ) ) {
            $kept = self::kept_in_place( $hook );

            if ( null !== $kept ) {
                self::with_hook_as(
                    $hook,
                    $kept,
                    static function () use ( $hook, $args ) {
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's hook, named by the caller.
                        do_action( $hook, ...$args );
                    }
                );
            }

            return;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's hook, named by the caller.
        do_action( $hook, ...$args );
    }

    /**
     * Runs $render with every callback detached from those $hooks that a Hook block fires, for a
     * WooCommerce template that fires them itself.
     *
     * @param string[] $hooks Hooks the template fires.
     */
    public static function without_placed( array $hooks, callable $render ): void {
        $saved = self::detach_placed( $hooks );

        try {
            $render();
        } finally {
            self::reattach( $saved );
        }
    }

    /**
     * On woocommerce_before_template_part: WooCommerce's review table, printed by an Order Summary without
     * blocks or built for update_order_review, fires the refreshed hooks a Hook block prints, so they are
     * detached until the template ends.
     *
     * @param mixed $template_name The template being printed.
     */
    public static function mute_review_table( $template_name ): void {
        if ( self::REVIEW_TEMPLATE === $template_name ) {
            self::$muted[] = self::detach_placed( self::placed_refreshed() );
        }
    }

    /**
     * On woocommerce_after_template_part: puts back what mute_review_table() detached.
     *
     * @param mixed $template_name The template that was printed.
     */
    public static function unmute_review_table( $template_name ): void {
        if ( self::REVIEW_TEMPLATE === $template_name && array() !== self::$muted ) {
            self::reattach( array_pop( self::$muted ) );
        }
    }

    /**
     * Takes every callback off those $hooks a Hook block fires.
     *
     * @param string[] $hooks
     * @return array<string, \WP_Hook> What reattach() puts back.
     */
    private static function detach_placed( array $hooks ): array {
        global $wp_filter;

        $saved = [];
        foreach ( $hooks as $hook ) {
            if ( self::is_placed( $hook ) && isset( $wp_filter[ $hook ] ) ) {
                $kept           = self::kept_in_place( $hook );
                $saved[ $hook ] = $wp_filter[ $hook ];

                if ( null === $kept ) {
                    unset( $wp_filter[ $hook ] );
                } else {
                    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reattach() restores the original.
                    $wp_filter[ $hook ] = $kept;
                }
            }
        }

        return $saved;
    }

    /** @param array<string, \WP_Hook> $saved From detach_placed(). */
    private static function reattach( array $saved ): void {
        global $wp_filter;

        foreach ( $saved as $hook => $wp_hook ) {
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the hook detach_placed() took off.
            $wp_filter[ $hook ] = $wp_hook;
        }
    }

    /**
     * Runs $render while $hook lacks $callbacks. It fires a copy of the hook, so the original, with its
     * callback order, is put back untouched even when a callback throws.
     *
     * @param callable[] $callbacks
     */
    private static function with_hook_trimmed( string $hook, array $callbacks, callable $render ): void {
        global $wp_filter;

        if ( array() === $callbacks || ! isset( $wp_filter[ $hook ] ) ) {
            $render();
            return;
        }

        $trimmed = clone $wp_filter[ $hook ];

        foreach ( $callbacks as $callback ) {
            $priority = $trimmed->has_filter( $hook, $callback );
            if ( false !== $priority ) {
                $trimmed->remove_filter( $hook, $callback, $priority );
            }
        }

        self::with_hook_as( $hook, $trimmed, $render );
    }

    /** A copy of $hook holding only its anchored callbacks, or null when it has none attached. */
    private static function kept_in_place( string $hook ): ?\WP_Hook {
        global $wp_filter;

        $anchored = self::$anchored[ $hook ] ?? [];

        if ( array() === $anchored || ! isset( $wp_filter[ $hook ] ) ) {
            return null;
        }

        $kept  = clone $wp_filter[ $hook ];
        $found = false;

        foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( in_array( $callback['function'], $anchored, true ) ) {
                    $found = true;
                } else {
                    $kept->remove_filter( $hook, $callback['function'], $priority );
                }
            }
        }

        return $found ? $kept : null;
    }

    /** Runs $render with $replacement standing in for $hook, then puts the original back, even when a callback throws. */
    private static function with_hook_as( string $hook, \WP_Hook $replacement, callable $render ): void {
        global $wp_filter;

        $original = $wp_filter[ $hook ] ?? null;

        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Swapped back in the finally below.
        $wp_filter[ $hook ] = $replacement;

        try {
            $render();
        } finally {
            if ( null === $original ) {
                unset( $wp_filter[ $hook ] );
            } else {
                // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the hook swapped above.
                $wp_filter[ $hook ] = $original;
            }
        }
    }
}
