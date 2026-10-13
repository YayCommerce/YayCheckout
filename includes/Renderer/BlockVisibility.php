<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Shows or hides any block in a layout by the open step, the cart and login (the block attribute
 * yaycheckoutVisibility). PHP owns the rules; packages/blocks/src/shared/visibility-rules.ts mirrors
 * normalize() and is_visible() for the storefront script and the editor preview.
 *
 * The server decides each block with step 1 open and prints a failing block with HIDDEN_CLASS. Every
 * conditional block carries its cleaned setting in DATA_ATTR, and the footer prints the cart state, which
 * update_order_review swaps as a fragment, so the storefront script re-decides on step and cart changes.
 */
class BlockVisibility {

    use SingletonTrait;

    /** The block attribute holding the setting. Mirrored by ATTRIBUTE in visibility-rules.ts. */
    public const ATTRIBUTE = 'yaycheckoutVisibility';

    /** On a conditional block's first tag: its cleaned setting as JSON. Mirrored by DATA_ATTR in visibility-rules.ts. */
    public const DATA_ATTR = 'data-yaycheckout-visibility';

    /** On a conditional block while hidden; checkout-base.css hides it. Mirrored by HIDDEN_CLASS in visibility-rules.ts. */
    public const HIDDEN_CLASS = 'yaycheckout-hidden-by-visibility';

    /** The id of the footer's JSON state element, and with `#` its update_order_review fragment. Mirrored by STATE_ID in visibility-rules.ts. */
    public const STATE_ID = 'yaycheckout-visibility-state';

    /** The storefront script, packages/blocks/src/visibility/view.ts; registered by BlockRegistry. */
    public const SCRIPT = 'yaycheckout-visibility';

    /** Blocks that never take the setting: step navigation can't skip a step. Mirrored by EXCLUDED in visibility-rules.ts. */
    public const EXCLUDED = [ 'yaycheckout/checkout', 'yaycheckout/steps', 'yaycheckout/step' ];

    /** Mirrored by RULE_TYPES in visibility-rules.ts. */
    public const RULE_TYPES = [ 'step', 'needs_shipping', 'has_coupon', 'subtotal', 'logged_in' ];

    /** Set once filter_block() decorated a block, so the footer prints the state the script reads. */
    private $rendered = false;

    protected function __construct() {
        add_filter( 'render_block', [ $this, 'filter_block' ], 10, 2 );
        add_action( 'wp_footer', [ $this, 'print_state' ] );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
    }

    /**
     * The cleaned setting: the action and its rules with only their known keys, amount as a float and step
     * as an int unless "last". Null for an absent, empty or malformed setting, which leaves the block shown.
     *
     * @param mixed $value The block attribute as parsed.
     * @return array{action: string, rules: array<int, array>}|null
     */
    public static function normalize( $value ): ?array {
        if ( ! is_array( $value ) || ! in_array( $value['action'] ?? null, [ 'show', 'hide' ], true ) ) {
            return null;
        }

        $rules = $value['rules'] ?? null;

        if ( ! is_array( $rules ) || [] === $rules || ! array_is_list( $rules ) ) {
            return null;
        }

        $clean = [];

        foreach ( $rules as $rule ) {
            $type = is_array( $rule ) ? ( $rule['type'] ?? null ) : null;

            if ( ! in_array( $type, self::RULE_TYPES, true ) || isset( $clean[ $type ] ) ) {
                return null;
            }

            $clean[ $type ] = self::normalize_rule( $type, $rule );

            if ( null === $clean[ $type ] ) {
                return null;
            }
        }

        return [
            'action' => $value['action'],
            'rules'  => array_values( $clean ),
        ];
    }

    /**
     * What the rules test for the current cart and shopper. The subtotal is the cart's as the cart shows it,
     * rounded to the store's decimals, so the script compares it as is. Without a cart, all false and 0.
     *
     * @return array{needs_shipping: bool, has_coupon: bool, subtotal: float, logged_in: bool}
     */
    public static function state(): array {
        $cart = function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ? WC()->cart : null;

        return [
            'needs_shipping' => null !== $cart && $cart->needs_shipping(),
            'has_coupon'     => null !== $cart && ! empty( $cart->get_applied_coupons() ),
            'subtotal'       => null !== $cart ? round( LiveValues::money()['cart_subtotal'] ?? 0.0, wc_get_price_decimals() ) : 0.0,
            'logged_in'      => is_user_logged_in(),
        ];
    }

    /**
     * True when the block shows: action "show" and every rule matches, or action "hide" and not every rule does.
     *
     * @param array $setting    A normalize()d setting.
     * @param array $state      As state() returns it.
     * @param int   $step       The open step, 1-based.
     * @param int   $step_count The layout's steps; 0 without a Steps block, when no step rule matches.
     */
    public static function is_visible( array $setting, array $state, int $step, int $step_count ): bool {
        $all = true;

        foreach ( $setting['rules'] as $rule ) {
            if ( ! self::matches( $rule, $state, $step, $step_count ) ) {
                $all = false;
                break;
            }
        }

        return ( 'show' === $setting['action'] ) === $all;
    }

    /**
     * Marks a conditional block with its setting for the script, and hides it when it fails with step 1
     * open. Anything else comes back untouched: no layout, an excluded block, no valid setting, or no HTML.
     *
     * @param string $block_content Rendered block.
     * @param array  $block         Parsed block.
     */
    public function filter_block( $block_content, $block ): string {
        $block_content = (string) $block_content;

        if ( '' === $block_content || ! is_array( $block ) || in_array( $block['blockName'] ?? null, self::EXCLUDED, true ) ) {
            return $block_content;
        }

        $setting = self::normalize( $block['attrs'][ self::ATTRIBUTE ] ?? null );

        if ( null === $setting || null === CheckoutPage::get_instance()->layout() ) {
            return $block_content;
        }

        $html = new \WP_HTML_Tag_Processor( $block_content );

        if ( ! $html->next_tag() ) {
            return $block_content;
        }

        $html->set_attribute( self::DATA_ATTR, wp_json_encode( $setting ) );

        if ( ! self::is_visible( $setting, self::state(), 1, self::step_count() ) ) {
            $html->add_class( self::HIDDEN_CLASS );
        }

        $this->rendered = true;
        // The layout renders before wp_head(), so the script is enqueued in time.
        wp_enqueue_script( self::SCRIPT );

        return $html->get_updated_html();
    }

    /** Prints the state for the script, once a block was decorated. */
    public function print_state(): void {
        if ( $this->rendered ) {
            echo self::state_element(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG and JSON_HEX_AMP, so it can't close the element.
        }
    }

    /** Swaps the footer's state for the recalculated cart's, when the served layout holds a setting. */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout || false === strpos( $layout->content, self::ATTRIBUTE ) ) {
            return $fragments;
        }

        $fragments[ '#' . self::STATE_ID ] = self::state_element();

        return $fragments;
    }

    /** The state() and the layout's step count as a JSON element; `<` and `&` are escaped, so nothing can close it. */
    private static function state_element(): string {
        return '<script type="application/json" id="' . esc_attr( self::STATE_ID ) . '">'
            . wp_json_encode( self::state() + [ 'step_count' => self::step_count() ], JSON_HEX_TAG | JSON_HEX_AMP )
            . '</script>';
    }

    /** The steps in the served layout's first Steps block; 0 without one. Memoised by layout_titles(). */
    private static function step_count(): int {
        return count( CheckoutStepsProgress::layout_titles() );
    }

    /**
     * One rule with only its known keys, or null when its parameters are invalid.
     *
     * @param string $type A RULE_TYPES entry.
     * @param array  $rule The rule as parsed.
     */
    private static function normalize_rule( string $type, array $rule ): ?array {
        if ( 'step' === $type ) {
            $step = $rule['step'] ?? null;

            if ( 'last' === $step ) {
                return [
                    'type' => $type,
                    'step' => 'last',
                ];
            }

            // An integral float too, as JSON may write it and visibility-rules.ts accepts it.
            if ( ( ! is_int( $step ) && ! is_float( $step ) ) || floor( $step ) !== (float) $step || $step < 1 ) {
                return null;
            }

            return [
                'type' => $type,
                'step' => (int) $step,
            ];
        }

        if ( 'subtotal' === $type ) {
            $op     = $rule['op'] ?? null;
            $amount = $rule['amount'] ?? null;

            if ( ! in_array( $op, [ 'gte', 'lt' ], true ) || ( ! is_int( $amount ) && ! is_float( $amount ) ) || ! is_finite( (float) $amount ) || $amount < 0 ) {
                return null;
            }

            return [
                'type'   => $type,
                'op'     => $op,
                'amount' => (float) $amount,
            ];
        }

        return [ 'type' => $type ];
    }

    /**
     * True when one rule holds for the state and the open step.
     *
     * @param array $rule A normalize()d rule.
     */
    private static function matches( array $rule, array $state, int $step, int $step_count ): bool {
        switch ( $rule['type'] ) {
            case 'step':
                return $step_count > 0 && ( $rule['step'] === $step || ( 'last' === $rule['step'] && $step === $step_count ) );
            case 'subtotal':
                $subtotal = (float) ( $state['subtotal'] ?? 0.0 );

                return 'gte' === $rule['op'] ? $subtotal >= $rule['amount'] : $subtotal < $rule['amount'];
            default:
                return ! empty( $state[ $rule['type'] ] );
        }
    }
}
